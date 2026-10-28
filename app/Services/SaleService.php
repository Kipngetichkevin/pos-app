<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\StockLevel;
use App\Models\Till;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(private StockService $stock)
    {
    }

    /**
     * @param array $items    [['product_id' => ..., 'quantity' => ..., 'product_unit_id' => null|...], ...]
     * @param array $payments [['method' => 'cash'|'mpesa'|'card', 'amount' => ..., 'reference' => ...], ...]
     */
    public function complete(array $items, array $payments, User $user, Shift $shift, ?string $customerId = null): Sale
    {
        if (empty($items)) {
            throw ValidationException::withMessages(['cart' => 'The cart is empty.']);
        }

        $sale = DB::transaction(function () use ($items, $payments, $user, $shift, $customerId) {
            $till = Till::with('branch')->whereKey($shift->till_id)->lockForUpdate()->firstOrFail();
            $till->receipt_sequence = $till->receipt_sequence + 1;
            $till->save();

            $receiptNo = sprintf('%s-%s-%06d', $till->branch->code, $till->code, $till->receipt_sequence);

            $products = Product::with('taxRate')
                ->whereIn('id', collect($items)->pluck('product_id'))
                ->get()
                ->keyBy('id');

            $units = ProductUnit::whereIn('id', collect($items)->pluck('product_unit_id')->filter()->values())
                ->get()
                ->keyBy('id');

            $wholesaleCustomer = $customerId && Customer::whereKey($customerId)->value('price_level') === 'wholesale';

            $lines = [];
            $subtotal = 0;
            $taxTotal = 0;

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);

                if (! $product || ! $product->is_active) {
                    throw ValidationException::withMessages(['cart' => 'A product in the cart is no longer available.']);
                }

                $unitId = $item['product_unit_id'] ?? null;
                $unit = $unitId ? $units->get($unitId) : null;

                if ($unitId && (! $unit || $unit->product_id !== $product->id || ! $unit->is_active || $unit->selling_price === null)) {
                    throw ValidationException::withMessages(['cart' => "{$product->name}: this packaging can no longer be sold."]);
                }

                $qty = round((float) $item['quantity'], 3);

                if ($qty <= 0) {
                    throw ValidationException::withMessages(['cart' => "Invalid quantity for {$product->name}."]);
                }

                $per = $unit ? (float) $unit->quantity : 1.0;
                $baseQty = round($qty * $per, 3);
                $price = $unit ? (float) $unit->selling_price : $product->priceFor($qty, $wholesaleCustomer);
                $lineTotal = round($price * $qty, 2);
                $rate = (float) ($product->taxRate?->rate ?? 0);
                $tax = $rate > 0 ? round($lineTotal * $rate / (100 + $rate), 2) : 0;

                $avgBase = (float) (StockLevel::where('branch_id', $shift->branch_id)
                    ->where('product_id', $product->id)
                    ->value('average_cost') ?: $product->cost_price);

                $subtotal += $lineTotal;
                $taxTotal += $tax;

                $lines[] = [
                    'product' => $product,
                    'unit' => $unit,
                    'qty' => $qty,
                    'baseQty' => $baseQty,
                    'price' => $price,
                    'lineTotal' => $lineTotal,
                    'rate' => $rate,
                    'tax' => $tax,
                    'avgBase' => $avgBase,
                    'unitCost' => round($avgBase * $per, 2),
                ];
            }

            $grandTotal = round($subtotal, 2);
            $paid = round(collect($payments)->sum('amount'), 2);
            $nonCash = round(collect($payments)->where('method', '!=', 'cash')->sum('amount'), 2);

            if ($paid < $grandTotal) {
                throw ValidationException::withMessages(['payment' => 'Amount paid is less than the total.']);
            }

            if ($nonCash > $grandTotal) {
                throw ValidationException::withMessages(['payment' => 'M-Pesa or card amount cannot be more than the total.']);
            }

            $change = round($paid - $grandTotal, 2);

            $sale = Sale::create([
                'receipt_no' => $receiptNo,
                'branch_id' => $shift->branch_id,
                'till_id' => $shift->till_id,
                'shift_id' => $shift->id,
                'user_id' => $user->id,
                'customer_id' => $customerId,
                'status' => 'completed',
                'subtotal' => round($subtotal, 2),
                'discount_total' => 0,
                'tax_total' => round($taxTotal, 2),
                'grand_total' => $grandTotal,
                'amount_paid' => $paid,
                'change_given' => $change,
                'etims_status' => 'pending',
                'sold_at' => now(),
            ]);

            foreach ($lines as $line) {
                $sale->items()->create([
                    'product_id' => $line['product']->id,
                    'product_unit_id' => $line['unit']?->id,
                    'product_name' => $line['product']->name,
                    'unit_name' => $line['unit']?->name,
                    'quantity' => $line['qty'],
                    'base_quantity' => $line['baseQty'],
                    'unit_price' => $line['price'],
                    'unit_cost' => $line['unitCost'],
                    'discount' => 0,
                    'tax_rate' => $line['rate'],
                    'tax_amount' => $line['tax'],
                    'line_total' => $line['lineTotal'],
                ]);

                if ($line['product']->track_stock) {
                    $this->stock->move(
                        product: $line['product'],
                        branchId: $shift->branch_id,
                        quantity: -$line['baseQty'],
                        type: 'sale',
                        userId: $user->id,
                        unitCost: $line['avgBase'],
                        reference: $sale,
                    );
                }
            }

            foreach ($payments as $payment) {
                $amount = round((float) $payment['amount'], 2);

                if ($payment['method'] === 'cash') {
                    $amount = round($amount - $change, 2);
                }

                if ($amount <= 0) {
                    continue;
                }

                $sale->payments()->create([
                    'method' => $payment['method'],
                    'amount' => $amount,
                    'reference' => $payment['reference'] ?? null,
                    'status' => 'confirmed',
                ]);
            }

            // Credit (deni): add to what the customer owes
            $credit = round(collect($payments)->where('method', 'credit')->sum('amount'), 2);

            if ($credit > 0) {
                $customer = $customerId ? Customer::find($customerId) : null;

                if (! $customer) {
                    throw ValidationException::withMessages(['payment' => 'A credit sale needs a customer.']);
                }

                CustomerService::record($customer, 'sale', $credit, [
                    'sale_id' => $sale->id,
                    'shift_id' => $shift->id,
                    'notes' => 'Credit sale ' . $sale->receipt_no,
                ]);
            }

            return $sale;
        });

        // Queued for KRA after the sale is saved; checkout never waits for eTIMS
        app(EtimsService::class)->queueSale($sale);

        return $sale;
    }
}

