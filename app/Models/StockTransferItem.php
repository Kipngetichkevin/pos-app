<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    use HasUuids;

    protected $table = 'stock_transfer_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity_sent' => 'decimal:3',
            'quantity_received' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'synced_at' => 'datetime',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Lost or damaged on the way (in base units). */
    public function shortfall(): float
    {
        return $this->quantity_received === null ? 0 : round((float) $this->quantity_sent - (float) $this->quantity_received, 3);
    }
}
