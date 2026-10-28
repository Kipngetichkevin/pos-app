<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtimsInvoice extends Model
{
    use HasUuids;

    protected $table = 'etims_invoices';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response' => 'array',
            'signed_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    /** Link a customer scans to check the invoice with KRA. */
    public function qrUrl(): ?string
    {
        if (! $this->isSent() || ! $this->receipt_signature) {
            return null;
        }

        return config('etims.qr_base') . config('etims.pin') . config('etims.branch_id') . $this->receipt_signature;
    }
}
