<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ReturnService
{
    public const REASONS = [
        'Wrong item',
        'Damaged / faulty',
        'Expired',
        'Customer changed mind',
        'Overcharged / wrong price',
        'Cashier error',
        'Other',
    ];

    public function __construct(private StockService $stock)
    {
    }

    /** The manager who approves: the user themselves if a manager, otherwise whoever owns the PIN. */
    public function approver(User $user, ?string $pin): User
    {
        if ($user->isManager()) {
            return $user;
        }

        if (blank($pin)) {
            throw ValidationException::withMessages(['pin' => 'A manager must enter their PIN to approve this.']);
        }

        $manager = User::whereIn('role', ['admin', 'manager'])
            ->where('is_active', true)
            ->whereNotNull('pin')
            ->get()
            ->first(fn (User $m) => Hash::check($pin, $m->pin));

        if (! $manager) {
            throw ValidationException::withMessages(['pin' => 'Wrong manager PIN.']);
        }

        return $manager;
    }

    /** A whole sale can be voided only before any return, while its shift is still open. */
    public function canVoid(Sale $sale): bool
    {
        return $sale->status === 'completed'
            && $sale->returns()->doesntExist()
            && $sale->shift?->status === 'open';
    }

    /**
     * @param array $quantities [sale_item_id => quantity to return]
     * @param array $restock    [sale_item_id => bool] false = damaged, do not put back on the shelf
     */
    public function process(Sale $sale, array $quantities, array $restock, string $reason, string $method, ?string $reference, User $user, User $approver): SaleReturn
    {
        if ($sale->status === 'voided') {
            throw ValidationException::withMessages(['sale' => 'This sale was voided.']);
        }

        if ($method === 'credit' && ! $sale->customer_id) {
            throw ValidationException::withMessages(['refundMethod' => 'Only sales to a customer can reduce their debt.']);
        }

        $shift = Shift::where('user_id', $user->id)->where('status', 'open')->latest('opened_at')->first();

        if ($method === 'cash' && ! $shift) {
            throw ValidationException::withMessages(['refundMethod' => 'Open a shift on the POS first: the cash comes out of your till.']);
        }

        $return = DB::transaction(function () use ($sale, $quantities, $restock, $reason, $method, $reference, $user, $approver, $shift) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $items = SaleItem::where('sale_id', $sale->id)->lockForUpdate()->get()->keyBy('id');

            $lines = [];

            foreach ($quantities as $itemId => $qty) {
                $qty = round((float) $qty, 3);

                if ($qty <= 0) {
                    continue;
                }

                $item = $items->get($itemId);

                if (! $item) {
                    throw ValidationException::withMessages(['sale' => 'An item is not part of this sale.']);
                }

                $remaining = round((float) $item->quantity - (float) $item->quantity_returned, 3);

                if ($qty > $remaining + 0.0005) {
                    throw ValidationException::withMessages(["qty.{$itemId}" => 'Only ' . (float) $remaining . ' can still be returned.']);
                }

                // Refund what the customer actually paid (line total includes any discount).
                // The last return of a line refunds exactly what is left, so cents never drift.
                $refund = $qty >= $remaining - 0.0005
                    ? round((float) $item->line_total - (float) $item->returnItems()->sum('refund_amount'), 2)
                    : round((float) $item->line_total / (float) $item->quantity * $qty, 2);

                $lines[] = ['item' => $item, 'qty' => $qty, 'refund' => $refund, 'restock' => (bool) ($restock[$itemId] ?? false)];
            }

            if (! $lines) {
                throw ValidationException::withMessages(['sale' => 'Enter how many of each item are coming back.']);
            }

            $total = round(collect($lines)->sum('refund'), 2);

            if ($method === 'credit') {
                $customer = Customer::find($sale->customer_id);

                if ($total > (float) $customer->balance + 0.009) {
                    throw ValidationException::withMessages(['refundMethod' => "{$customer->name} owes only " . number_format((float) $customer->balance, 2) . '. Refund the rest in cash or M-Pesa.']);
                }
            }

            $return = SaleReturn::create([
                'return_no' => $sale->receipt_no . '-R' . ($sale->returns()->count() + 1),
                'sale_id' => $sale->id,
                'branch_id' => $sale->branch_id,
                'shift_id' => $shift?->id,
                'user_id' => $user->id,
                'approved_by' => $approver->id,
                'reason' => $reason,
                'refund_method' => $method,
                'refund_reference' => $reference ? strtoupper(trim($reference)) : null,
                'total_refunded' => $total,
                'returned_at' => now(),
            ]);

            foreach ($lines as $line) {
                $item = $line['item'];

                $return->items()->create([
                    'sale_item_id' => $item->id,
                    'quantity' => $line['qty'],
                    'refund_amount' => $line['refund'],
                    'restock' => $line['restock'],
                ]);

                $item->increment('quantity_returned', $line['qty']);

                if ($line['restock']) {
                    $this->restock($item, $line['qty'], $sale, $user, $return, 'Returned: ' . $reason);
                }
            }

            if ($method === 'credit') {
                CustomerService::record(Customer::find($sale->customer_id), 'refund', -$total, [
                    'sale_id' => $sale->id,
                    'shift_id' => $shift?->id,
                    'notes' => 'Return ' . $return->return_no,
                ]);
            }

            $allBack = SaleItem::where('sale_id', $sale->id)->whereColumn('quantity_returned', '<', 'quantity')->doesntExist();
            $sale->update(['status' => $allBack ? 'refunded' : 'partially_refunded']);

            return $return;
        });

        app(EtimsService::class)->queueCreditNote($return);

        return $return;
    }

    /** Cancel a whole sale: everything goes back into stock and credit is reversed. */
    public function void(Sale $sale, string $reason, User $user, User $approver): Sale
    {
        if (! $this->canVoid($sale)) {
            throw ValidationException::withMessages(['sale' => 'Only completed sales from an open shift, with no returns, can be voided. Use a return instead.']);
        }

        $sale = DB::transaction(function () use ($sale, $reason, $user, $approver) {
            $sale = Sale::with('items')->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            foreach ($sale->items as $item) {
                $this->restock($item, (float) $item->quantity, $sale, $user, $sale, 'Voided sale ' . $sale->receipt_no);
            }

            $credit = round((float) $sale->payments()->where('method', 'credit')->sum('amount'), 2);

            if ($credit > 0 && $sale->customer_id) {
                CustomerService::record(Customer::find($sale->customer_id), 'adjustment', -$credit, [
                    'sale_id' => $sale->id,
                    'shift_id' => $sale->shift_id,
                    'notes' => 'Voided sale ' . $sale->receipt_no,
                ]);
            }

            $sale->update([
                'status' => 'voided',
                'voided_by' => $approver->id,
                'void_reason' => $reason,
            ]);

            return $sale;
        });

        app(EtimsService::class)->voidSale($sale);

        return $sale;
    }

    protected function restock(SaleItem $item, float $qty, Sale $sale, User $user, $reference, string $reason): void
    {
        $product = Product::find($item->product_id);

        if (! $product || ! $product->track_stock) {
            return;
        }

        // Packaging lines (e.g. 1 carton) go back in base units (e.g. 12 bottles)
        $perUnit = (float) $item->quantity > 0 ? (float) ($item->base_quantity ?: $item->quantity) / (float) $item->quantity : 1;
        $baseQty = round($qty * $perUnit, 3);

        if ($baseQty <= 0) {
            return;
        }

        $this->stock->move(
            product: $product,
            branchId: $sale->branch_id,
            quantity: $baseQty,
            type: 'return',
            userId: $user->id,
            unitCost: $perUnit > 0 ? round((float) $item->unit_cost / $perUnit, 2) : null,
            reference: $reference,
            reason: $reason,
        );
    }
}
