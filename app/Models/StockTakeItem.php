<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTakeItem extends Model
{
    use HasUuids;

    protected $table = 'stock_take_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expected_qty' => 'decimal:3',
            'counted_qty' => 'decimal:3',
            'variance' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'counted_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(StockTake::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    /** Shortage (negative) or excess (positive) in money. */
    public function varianceValue(): float
    {
        return round((float) $this->variance * (float) $this->unit_cost, 2);
    }
}
