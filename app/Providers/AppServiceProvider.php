<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useTailwind();

        $this->registerAuditTrail();
    }

    /**
     * Sensitive changes are logged from model events, so every screen (and future ones) is covered.
     * Rows written by the sync engine use the query builder and are not logged twice.
     */
    protected function registerAuditTrail(): void
    {
        $money = fn ($v) => $v === null ? 'none' : number_format((float) $v, 2);

        Product::updated(function (Product $product) use ($money) {
            if ($changes = AuditLog::diff($product, ['selling_price', 'wholesale_price', 'cost_price'])) {
                $text = collect($changes)->map(fn ($c, $k) => str_replace('_', ' ', $k) . ' ' . $money($c[0]) . ' → ' . $money($c[1]))->join(', ');
                AuditLog::record('price.changed', "{$product->name}: {$text}", $product, $changes);
            }
        });

        ProductUnit::updated(function (ProductUnit $unit) use ($money) {
            if ($changes = AuditLog::diff($unit, ['selling_price'])) {
                AuditLog::record('price.changed', "{$unit->product?->name} ({$unit->name}): price {$money($changes['selling_price'][0])} → {$money($changes['selling_price'][1])}", $unit, $changes);
            }
        });

        Customer::updated(function (Customer $customer) use ($money) {
            if ($changes = AuditLog::diff($customer, ['credit_limit'])) {
                AuditLog::record('credit_limit.changed', "{$customer->name}: credit limit {$money($changes['credit_limit'][0])} → {$money($changes['credit_limit'][1])}", $customer, $changes);
            }
        });

        User::updated(function (User $user) {
            if ($changes = AuditLog::diff($user, ['role', 'is_active', 'branch_id'])) {
                $parts = [];
                if (isset($changes['role'])) $parts[] = "role {$changes['role'][0]} → {$changes['role'][1]}";
                if (isset($changes['is_active'])) $parts[] = $user->is_active ? 'activated' : 'deactivated';
                if (isset($changes['branch_id'])) $parts[] = 'moved to ' . ($user->branch?->name ?? 'another branch');
                AuditLog::record('user.changed', "{$user->name}: " . implode(', ', $parts), $user, $changes);
            }
        });

        Sale::updated(function (Sale $sale) {
            if ($sale->wasChanged('status') && $sale->status === 'voided') {
                AuditLog::record('sale.voided', "Voided {$sale->receipt_no} (KSh " . number_format((float) $sale->grand_total, 2) . ')' . ($sale->void_reason ? ": {$sale->void_reason}" : ''), $sale);
            }
        });

        SaleReturn::created(function (SaleReturn $return) {
            AuditLog::record('sale.returned', "Return {$return->return_no}: refunded KSh " . number_format((float) $return->total_refunded, 2) . " by {$return->refund_method} ({$return->reason})", $return);
        });

        SaleItem::created(function (SaleItem $item) {
            $cost = round((float) $item->unit_cost * (float) $item->quantity, 2);

            if ((float) $item->line_total < $cost) {
                AuditLog::record('sale.below_cost', "{$item->product_name}: sold for " . number_format((float) $item->line_total, 2) . ', cost ' . number_format($cost, 2) . " ({$item->sale?->receipt_no})", $item->sale);
            }
        });

        SalePayment::saved(function (SalePayment $payment) {
            if ($payment->method === 'mpesa' && $payment->status === 'pending' && $payment->wasChanged('status')) {
                AuditLog::record('mpesa.unverified', "Accepted M-Pesa code {$payment->reference} without verification (KSh " . number_format((float) $payment->amount, 2) . ", {$payment->sale?->receipt_no})", $payment->sale);
            }
        });

        StockMovement::created(function (StockMovement $movement) {
            if (in_array($movement->type, ['adjustment', 'damage'])) {
                $qty = rtrim(rtrim(number_format((float) $movement->quantity, 3), '0'), '.');
                AuditLog::record('stock.adjusted', "{$movement->product?->name}: " . ((float) $movement->quantity > 0 ? '+' : '') . "{$qty}" . ($movement->reason ? " ({$movement->reason})" : ''), $movement->product);
            }
        });

        Expense::deleted(function (Expense $expense) {
            AuditLog::record('expense.removed', "Removed expense: {$expense->description} (KSh " . number_format((float) $expense->amount, 2) . ')', $expense);
        });

        Shift::updated(function (Shift $shift) {
            if ($shift->wasChanged('status') && $shift->status === 'closed' && round((float) $shift->variance, 2) != 0) {
                $v = (float) $shift->variance;
                AuditLog::record('shift.closed', "Till {$shift->till?->code} closed " . ($v < 0 ? 'SHORT' : 'OVER') . ' by KSh ' . number_format(abs($v), 2) . ($shift->notes ? ": {$shift->notes}" : ''), $shift);
            }
        });
    }
}
