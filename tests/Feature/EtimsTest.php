<?php

namespace Tests\Feature;

use App\Models\EtimsInvoice;
use App\Models\Sale;
use App\Services\EtimsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\PosTestCase;

class EtimsTest extends PosTestCase
{
    protected function sell(array $items, float $cash): Sale
    {
        $this->actingAs($this->admin)->postJson('/pos/checkout', ['items' => $items, 'cash' => $cash])->assertOk();

        return Sale::latest('sold_at')->latest('receipt_no')->firstOrFail();
    }

    public function test_switched_off_marks_sales_not_required(): void
    {
        config(['etims.driver' => 'off']);
        $this->openShift($this->admin);

        $sale = $this->sell([['product_id' => $this->product()->id, 'quantity' => 1]], 150);

        $this->assertSame('not_required', $sale->etims_status);
        $this->assertSame(0, EtimsInvoice::count());
    }

    public function test_invoice_then_credit_note_with_gap_free_numbers(): void
    {
        config(['etims.driver' => 'fake', 'etims.pin' => 'P051234567X']);
        Bus::fake();
        $this->openShift($this->admin);
        $product = $this->product('Juice 1L', 116, 80, 10);

        $sale = $this->sell([['product_id' => $product->id, 'quantity' => 2]], 232);
        $this->assertSame('pending', $sale->fresh()->etims_status);

        app(EtimsService::class)->sendDue();

        $invoice = EtimsInvoice::where('type', 'invoice')->firstOrFail();
        $this->assertSame('sent', $invoice->status);
        $this->assertSame(1, (int) $invoice->invoice_no);
        $this->assertSame('submitted', $sale->fresh()->etims_status);
        $this->assertNotNull($product->fresh()->etims_item_code);

        // VAT 16% inclusive: 232 taxable in bucket B, 32 of it is VAT
        $this->assertSame(232.0, (float) $invoice->payload['taxblAmtB']);
        $this->assertSame(32.0, (float) $invoice->payload['taxAmtB']);
        $this->assertSame('01', $invoice->payload['pmtTyCd']);

        $this->actingAs($this->admin)->get("/receipts/{$sale->id}")->assertOk()
            ->assertSee('KRA eTIMS INVOICE')->assertSee('TRAINING - NOT A TAX INVOICE');

        // Refund one: credit note 2 reversing invoice 1
        $item = $sale->items()->first();
        $this->actingAs($this->admin)->post("/returns/{$sale->id}", [
            'qty' => [$item->id => 1], 'restock' => [$item->id => 1], 'reason' => 'Damaged / faulty', 'refundMethod' => 'cash',
        ])->assertSessionHasNoErrors();

        app(EtimsService::class)->sendDue();

        $note = EtimsInvoice::where('type', 'credit_note')->firstOrFail();
        $this->assertSame('sent', $note->status);
        $this->assertSame(2, (int) $note->invoice_no);
        $this->assertSame(1, (int) $note->original_invoice_no);
        $this->assertSame('R', $note->payload['rcptTyCd']);
        $this->assertSame(116.0, (float) $note->payload['totAmt']);

        $return = $sale->returns()->first();
        $this->actingAs($this->admin)->get("/returns/{$return->id}/receipt")->assertOk()->assertSee('KRA eTIMS CREDIT NOTE');
    }

    public function test_void_before_kra_got_it_is_simply_not_sent(): void
    {
        config(['etims.driver' => 'fake']);
        Bus::fake();
        $this->openShift($this->admin);

        $sale = $this->sell([['product_id' => $this->product()->id, 'quantity' => 1]], 150);
        $this->actingAs($this->admin)->post("/returns/{$sale->id}/void", ['reason' => 'Cashier error'])->assertSessionHasNoErrors();

        app(EtimsService::class)->sendDue();

        $this->assertSame('not_required', EtimsInvoice::firstOrFail()->status);
        $this->assertSame(1, EtimsInvoice::count()); // no credit note needed
    }

    public function test_void_after_kra_got_it_issues_a_full_credit_note(): void
    {
        config(['etims.driver' => 'fake']);
        Bus::fake();
        $this->openShift($this->admin);

        $sale = $this->sell([['product_id' => $this->product()->id, 'quantity' => 2]], 300);
        app(EtimsService::class)->sendDue();

        $this->actingAs($this->admin)->post("/returns/{$sale->id}/void", ['reason' => 'Cashier error'])->assertSessionHasNoErrors();
        app(EtimsService::class)->sendDue();

        $note = EtimsInvoice::where('type', 'credit_note')->firstOrFail();
        $this->assertSame('sent', $note->status);
        $this->assertSame(300.0, (float) $note->payload['totAmt']);
    }

    public function test_no_internet_is_retried_later_and_checkout_still_works(): void
    {
        config(['etims.driver' => 'integrator', 'etims.integrator.url' => 'https://etims.example', 'etims.integrator.key' => 'k']);
        Bus::fake();
        $online = false;
        Http::fake(function () use (&$online) {
            return $online
                ? Http::response(['data' => ['rcptNo' => 77, 'intrlData' => 'ABC', 'rcptSign' => 'SIGN123', 'sdcId' => 'KRACU0100000001']])
                : throw new ConnectionException('offline');
        });
        $this->openShift($this->admin);

        $sale = $this->sell([['product_id' => $this->product()->id, 'quantity' => 1]], 150);
        app(EtimsService::class)->sendDue();

        $invoice = EtimsInvoice::firstOrFail();
        $this->assertSame('failed', $invoice->status);
        $this->assertNotNull($invoice->next_attempt_at); // will retry
        $this->assertSame('failed', $sale->fresh()->etims_status);

        // Internet back: the manager presses "Send now"
        $online = true;
        $this->actingAs($this->admin)->post("/etims/{$invoice->id}/retry")->assertSessionHas('status');

        $this->assertSame('sent', $invoice->fresh()->status);
        $this->assertSame((int) $invoice->invoice_no, (int) $invoice->fresh()->invoice_no); // same number on retry
        $this->actingAs($this->admin)->get('/etims?status=sent')->assertOk()->assertSee($sale->receipt_no);
    }

    public function test_oscu_driver_sends_kra_headers_and_stores_the_signature(): void
    {
        config(['etims.driver' => 'oscu', 'etims.pin' => 'P051234567X', 'etims.branch_id' => '00', 'etims.oscu.cmc_key' => 'CMC-KEY']);
        Bus::fake();
        Http::fake([
            '*/items/saveItems' => Http::response(['resultCd' => '000', 'resultMsg' => 'OK']),
            '*/trnsSales/saveSales' => Http::response(['resultCd' => '000', 'data' => [
                'curRcptNo' => 12, 'totRcptNo' => 12, 'intrlData' => 'INTERNALDATA', 'rcptSign' => 'SIGNATURE1', 'sdcId' => 'KRACU0100000001', 'mrcNo' => 'MRC1', 'sdcDateTime' => '20261021101500',
            ]]),
        ]);
        $this->openShift($this->admin);
        $product = $this->product();
        $product->update(['etims_class_code' => '5010150000']);

        $this->sell([['product_id' => $product->id, 'quantity' => 1]], 150);
        app(EtimsService::class)->sendDue();

        $invoice = EtimsInvoice::firstOrFail();
        $this->assertSame('sent', $invoice->status, (string) $invoice->last_error);
        $this->assertSame('SIGNATURE1', $invoice->receipt_signature);
        $this->assertStringEndsWith('P051234567X00SIGNATURE1', $invoice->qrUrl());

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/trnsSales/saveSales')
            && $r->header('cmcKey') === ['CMC-KEY'] && $r['tin'] === 'P051234567X' && $r['invcNo'] === 1);
    }

    public function test_oscu_rejects_unclassified_items_without_retrying(): void
    {
        config(['etims.driver' => 'oscu', 'etims.pin' => 'P051234567X', 'etims.oscu.cmc_key' => 'CMC-KEY']);
        Bus::fake();
        Http::fake();
        $this->openShift($this->admin);

        $this->sell([['product_id' => $this->product('Mystery item')->id, 'quantity' => 1]], 150);
        app(EtimsService::class)->sendDue();

        $invoice = EtimsInvoice::firstOrFail();
        $this->assertSame('failed', $invoice->status);
        $this->assertNull($invoice->next_attempt_at);
        $this->assertStringContainsString('classification for Mystery item', $invoice->last_error);
        Http::assertNothingSent();
    }
}
