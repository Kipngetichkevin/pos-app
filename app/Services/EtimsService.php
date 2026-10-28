<?php

namespace App\Services;

use App\Etims\Drivers\FakeDriver;
use App\Etims\Drivers\IntegratorDriver;
use App\Etims\Drivers\OscuDriver;
use App\Etims\EtimsDriver;
use App\Etims\EtimsException;
use App\Etims\InvoicePayload;
use App\Jobs\SendEtimsInvoice;
use App\Models\Branch;
use App\Models\EtimsInvoice;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * KRA eTIMS. Checkout never waits for KRA: invoices are queued here and sent in the background,
 * retried until KRA accepts them (internet down, KRA slow), and the receipt shows KRA's signature once it is back.
 */
class EtimsService
{
    public static function enabled(): bool
    {
        return config('etims.driver', 'off') !== 'off';
    }

    public function driver(): EtimsDriver
    {
        return match (config('etims.driver')) {
            'oscu' => app(OscuDriver::class),
            'integrator' => app(IntegratorDriver::class),
            'fake' => app(FakeDriver::class),
            default => throw new EtimsException('eTIMS is turned off (ETIMS_DRIVER=off).', retryable: false),
        };
    }

    public function queueSale(Sale $sale): ?EtimsInvoice
    {
        if (! self::enabled()) {
            $sale->update(['etims_status' => 'not_required']);

            return null;
        }

        $invoice = EtimsInvoice::create([
            'branch_id' => $sale->branch_id,
            'sale_id' => $sale->id,
            'type' => 'invoice',
            'status' => 'queued',
            'next_attempt_at' => now(),
        ]);

        SendEtimsInvoice::dispatchAfterResponse($invoice->id);

        return $invoice;
    }

    /** A refund reverses (part of) the original invoice with a credit note. */
    public function queueCreditNote(SaleReturn $return): ?EtimsInvoice
    {
        $original = EtimsInvoice::where('sale_id', $return->sale_id)->where('type', 'invoice')->first();

        if (! $original || $original->status === 'not_required') {
            return null;
        }

        $note = EtimsInvoice::create([
            'branch_id' => $return->branch_id,
            'sale_id' => $return->sale_id,
            'sale_return_id' => $return->id,
            'type' => 'credit_note',
            'status' => 'queued',
            'next_attempt_at' => now(),
        ]);

        SendEtimsInvoice::dispatchAfterResponse($note->id);

        return $note;
    }

    /** Voiding: if KRA never got the invoice, just don't send it. If it did, reverse it with a full credit note. */
    public function voidSale(Sale $sale): void
    {
        $original = EtimsInvoice::where('sale_id', $sale->id)->where('type', 'invoice')->first();

        if (! $original || $original->status === 'not_required') {
            return;
        }

        $cancelled = EtimsInvoice::whereKey($original->id)->whereIn('status', ['queued', 'failed'])->whereNull('invoice_no')
            ->update(['status' => 'not_required', 'last_error' => 'Sale voided before it reached KRA', 'next_attempt_at' => null]);

        if ($cancelled) {
            $sale->update(['etims_status' => 'not_required']);

            return;
        }

        $note = EtimsInvoice::create([
            'branch_id' => $sale->branch_id,
            'sale_id' => $sale->id,
            'type' => 'credit_note', // no sale_return_id = the whole sale
            'status' => 'queued',
            'next_attempt_at' => now(),
        ]);

        SendEtimsInvoice::dispatchAfterResponse($note->id);
    }

    /** Send one invoice now. Returns true when KRA accepted it. */
    public function send(EtimsInvoice $invoice): bool
    {
        // One sender per invoice at a time, so KRA never receives the same number twice
        $lock = Cache::lock('etims-send:' . $invoice->id, 120);

        if (! $lock->get()) {
            return false;
        }

        try {
            $invoice = $invoice->fresh();

            if (! $invoice || in_array($invoice->status, ['sent', 'not_required'])) {
                return (bool) $invoice?->isSent();
            }

            try {
                $this->prepare($invoice);
                $this->registerItems($invoice);

                // The network call happens outside any database transaction
                $payload = InvoicePayload::build($invoice);
                $result = $this->driver()->send($payload);
            } catch (Throwable $e) {
                $this->recordFailure($invoice, $e);

                return false;
            }

            $invoice->fill([
                'status' => 'sent',
                'attempts' => $invoice->attempts + 1,
                'last_error' => null,
                'next_attempt_at' => null,
                'payload' => $payload,
                'response' => array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v, $result),
                'kra_receipt_no' => $result['receipt_no'] ?? null,
                'kra_total_receipt_no' => $result['total_receipt_no'] ?? null,
                'internal_data' => $result['internal_data'] ?? null,
                'receipt_signature' => $result['signature'] ?? null,
                'sdc_id' => $result['sdc_id'] ?? null,
                'mrc_no' => $result['mrc_no'] ?? null,
                'signed_at' => $result['signed_at'] ?? now(),
            ])->save();

            if ($invoice->type === 'invoice') {
                Sale::whereKey($invoice->sale_id)->update(['etims_status' => 'submitted']);
            }

            return true;
        } finally {
            $lock->release();
        }
    }

    /** Checks before sending, and the invoice number (gap-free per branch, kept for every retry). */
    protected function prepare(EtimsInvoice $invoice): void
    {
        if ($invoice->type === 'credit_note') {
            $original = EtimsInvoice::where('sale_id', $invoice->sale_id)->where('type', 'invoice')->first();

            // KRA must have the invoice before it can accept a credit note against it
            if (! $original?->isSent()) {
                throw new EtimsException('Waiting for the original invoice to reach KRA first.');
            }

            $invoice->original_invoice_no = $original->invoice_no;
        }

        if (! $invoice->invoice_no) {
            DB::transaction(function () use ($invoice) {
                $branch = Branch::whereKey($invoice->branch_id)->lockForUpdate()->first();
                $branch->increment('etims_invoice_sequence');
                $invoice->invoice_no = $branch->etims_invoice_sequence;
                $invoice->save();
            });
        } else {
            $invoice->save();
        }
    }

    protected function recordFailure(EtimsInvoice $invoice, Throwable $e): void
    {
        $retryable = ! ($e instanceof EtimsException) || $e->retryable;
        $attempts = $invoice->attempts + 1;
        $giveUp = ! $retryable || $attempts >= config('etims.max_attempts');

        $invoice->fill([
            'status' => 'failed',
            'attempts' => $attempts,
            'last_error' => mb_substr($e->getMessage(), 0, 500),
            // Back off: 1, 2, 4 ... up to 60 minutes. Stop when retrying cannot help.
            'next_attempt_at' => $giveUp ? null : now()->addMinutes(min(2 ** min($attempts - 1, 6), 60)),
        ])->save();

        if ($invoice->type === 'invoice') {
            Sale::whereKey($invoice->sale_id)->update(['etims_status' => 'failed']);
        }
    }

    /** Send everything that is due. Run by the scheduler every minute. */
    public function sendDue(int $limit = 50): array
    {
        $done = ['sent' => 0, 'failed' => 0];

        EtimsInvoice::whereIn('status', ['queued', 'failed'])
            ->whereNotNull('next_attempt_at')
            ->where('next_attempt_at', '<=', now())
            // invoices before their credit notes
            ->orderByRaw("CASE WHEN type = 'invoice' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->each(function (EtimsInvoice $invoice) use (&$done) {
                $this->send($invoice) ? $done['sent']++ : $done['failed']++;
            });

        return $done;
    }

    /** A manager asks to try again (e.g. after fixing a product's classification). */
    public function retry(EtimsInvoice $invoice): void
    {
        if (in_array($invoice->status, ['queued', 'failed'])) {
            $invoice->update(['next_attempt_at' => now(), 'attempts' => 0]);
        }
    }

    /** Give products their KRA item code and register them before their first invoice. */
    protected function registerItems(EtimsInvoice $invoice): void
    {
        $productIds = DB::table('sale_items')->where('sale_id', $invoice->sale_id)->pluck('product_id')->unique();

        Product::with('taxRate')->whereIn('id', $productIds)->whereNull('etims_registered_at')->get()
            ->each(function (Product $product) {
                $code = $product->etims_item_code ?: $this->nextItemCode();
                $this->driver()->registerItem($product, $code);
                $product->forceFill(['etims_item_code' => $code, 'etims_registered_at' => now()])->saveQuietly();
            });
    }

    /** KE + product type 2 (finished good) + packaging NT + quantity unit XU + 7-digit sequence, e.g. KE2NTXU0000012. */
    protected function nextItemCode(): string
    {
        $last = Product::where('etims_item_code', 'like', 'KE2NTXU%')->max('etims_item_code');
        $next = $last ? (int) substr($last, 7) + 1 : 1;

        return 'KE2NTXU' . str_pad((string) $next, 7, '0', STR_PAD_LEFT);
    }
}
