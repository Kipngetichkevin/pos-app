<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasUuids;

    protected $table = 'products';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'wholesale_min_qty' => 'decimal:3',
            'is_weighed' => 'boolean',
            'track_stock' => 'boolean',
            'track_expiry' => 'boolean',
            'reorder_level' => 'decimal:3',
            'is_active' => 'boolean',
            'pack_size' => 'decimal:3',
            'synced_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function stockBatches(): HasMany
    {
        return $this->hasMany(StockBatch::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** The bulk product this pack is made from (e.g. "Sugar 1 kg" -> "Sugar") */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_id');
    }

    /** Pack sizes made from this bulk product */
    public function packs(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_id');
    }

    /** Packagings: Carton = 12 bottles, Packet = 10 pieces, etc. */
    public function packagings(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    /**
     * Price per base unit for this sale. Wholesale applies to wholesale customers,
     * or to anyone buying at least the wholesale quantity. Packagings (cartons) keep their own price.
     */
    public function priceFor(float $quantity, bool $wholesaleCustomer = false): float
    {
        $wholesale = $this->wholesale_price !== null ? (float) $this->wholesale_price : null;

        if ($wholesale !== null && ($wholesaleCustomer
            || ($this->wholesale_min_qty !== null && $quantity >= (float) $this->wholesale_min_qty))) {
            return $wholesale;
        }

        return (float) $this->selling_price;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

