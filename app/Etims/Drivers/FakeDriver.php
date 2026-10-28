<?php

namespace App\Etims\Drivers;

use App\Etims\EtimsDriver;
use App\Models\Product;
use Illuminate\Support\Str;

/** Accepts everything and makes up KRA-looking signatures. For training staff and testing only. */
class FakeDriver implements EtimsDriver
{
    public function registerItem(Product $product, string $itemCode): void
    {
    }

    public function send(array $payload): array
    {
        return [
            'receipt_no' => (string) $payload['invcNo'],
            'total_receipt_no' => (string) $payload['invcNo'],
            'internal_data' => 'TEST' . strtoupper(Str::random(22)),
            'signature' => 'TEST' . strtoupper(Str::random(12)),
            'sdc_id' => 'KRACU-TEST',
            'mrc_no' => 'TEST-MRC',
            'signed_at' => now(),
        ];
    }
}
