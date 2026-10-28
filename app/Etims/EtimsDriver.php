<?php

namespace App\Etims;

use App\Models\Product;

/** One way of reaching KRA eTIMS (directly via OSCU, through an integrator, or a fake for training). */
interface EtimsDriver
{
    /** Register a product with eTIMS so it can appear on invoices. */
    public function registerItem(Product $product, string $itemCode): void;

    /**
     * Send an invoice or credit note (payload from InvoicePayload).
     * Returns what KRA signed: receipt_no, total_receipt_no, internal_data, signature, sdc_id, mrc_no, signed_at.
     *
     * @throws EtimsException
     */
    public function send(array $payload): array;
}
