<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\MpesaTransaction;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasUuids, SoftDeletes;

    public const CATEGORIES = [
        'rent' => 'Rent',
        'electricity' => 'Electricity / KPLC tokens',
        'water' => 'Water',
        'wages' => 'Wages / casuals',
        'transport' => 'Transport / fuel',
        'airtime' => 'Airtime / internet',
        'supplies' => 'Shop supplies (bags, cleaning)',
        'repairs' => 'Repairs',
        'licences' => 'Licences / county fees',
        'meals' => 'Meals / tea',
        'bank' => 'Bank / M-Pesa charges',
        'other' => 'Other',
    ];

    public const METHODS = ['cash' => 'Cash from till', 'mpesa' => 'M-Pesa', 'bank' => 'Bank'];

    protected $table = 'expenses';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }
    public function mpesaTransaction(): BelongsTo

    {

        return $this->belongsTo(MpesaTransaction::class);

    }
}

