<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasUuids;

    protected $table = 'shifts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'variance' => 'decimal:2',
            'synced_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function till(): BelongsTo
    {
        return $this->belongsTo(Till::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** Totals for this shift. Cash payments are stored net of change, so they equal cash kept in the drawer. */
    public function summary(): array
    {
        $sales = $this->sales()->where('status', '!=', 'voided');
        $saleIds = (clone $sales)->select('id');

        $byMethod = SalePayment::whereIn('sale_id', $saleIds)
            ->selectRaw('method, SUM(amount) as total')
            ->groupBy('method')
            ->pluck('total', 'method');

        $cash = (float) ($byMethod['cash'] ?? 0);
        $cashRefunds = (float) SaleReturn::where('shift_id', $this->id)->where('refund_method', 'cash')->sum('total_refunded');
        $debtCash = (float) -CustomerTransaction::where('shift_id', $this->id)->where('type', 'payment')->where('method', 'cash')->sum('amount');
        $cashExpenses = (float) Expense::where('shift_id', $this->id)->where('method', 'cash')->sum('amount');

        return [
            'sales_count' => (clone $sales)->count(),
            'sales_total' => (float) (clone $sales)->sum('grand_total'),
            'cash' => $cash,
            'mpesa' => (float) ($byMethod['mpesa'] ?? 0),
            'credit' => (float) ($byMethod['credit'] ?? 0),
            'other' => (float) $byMethod->except(['cash', 'mpesa', 'credit'])->sum(),
            'unverified' => SalePayment::whereIn('sale_id', $saleIds)->where('method', 'mpesa')->where('status', 'pending')->count(),
            'voided' => $this->sales()->where('status', 'voided')->count(),
            'opening_float' => (float) $this->opening_float,
            'cash_refunds' => $cashRefunds,
            'debt_cash' => $debtCash,
            'cash_expenses' => $cashExpenses,
            'expected_cash' => round((float) $this->opening_float + $cash - $cashRefunds + $debtCash - $cashExpenses, 2),
        ];
    }
}


