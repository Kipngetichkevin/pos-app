<?php

namespace App\Sync;

/**
 * Which tables sync, in which direction, in parent-before-child order.
 *
 *  both = shared data edited anywhere (catalogue, customers, users). Newest edit wins.
 *  up   = owned by the branch that created it (sales, shifts, stock). Only branches send it; the cloud never sends it back.
 *  down = owned by the cloud (branch details). Only the cloud sends it.
 */
class SyncTables
{
    public const TABLES = [
        'branches' => 'down',
        'users' => 'both',
        'tills' => 'both',
        'categories' => 'both',
        'units' => 'both',
        'tax_rates' => 'both',
        'suppliers' => 'both',
        'products' => 'both',
        'product_barcodes' => 'both',
        'product_units' => 'both',
        'customers' => 'both',
        'mpesa_transactions' => 'both',
        'shifts' => 'up',
        'sales' => 'up',
        'sale_items' => 'up',
        'sale_payments' => 'up',
        'sale_returns' => 'up',
        'sale_return_items' => 'up',
        'customer_transactions' => 'both',
        'goods_receipts' => 'up',
        'goods_receipt_items' => 'up',
        'repacks' => 'up',
        'expenses' => 'up',
        'stock_takes' => 'up',
        'stock_take_items' => 'up',
        'audit_logs' => 'up',
        'etims_invoices' => 'up',
        'stock_transfers' => 'both',
        'stock_transfer_items' => 'both',
        'stock_batches' => 'up',
        'stock_levels' => 'up',
        'stock_movements' => 'up',
    ];

    /** Columns never copied from another server. */
    public const LOCAL_ONLY = [
        // The KRA invoice counter belongs to the branch server that sends the invoices
        'branches' => ['sync_token_hash', 'last_sync_at', 'etims_invoice_sequence'],
        'users' => ['remember_token'],
    ];

    public static function pushable(): array
    {
        return array_keys(array_filter(self::TABLES, fn ($d) => $d !== 'down'));
    }

    public static function pullable(): array
    {
        return array_keys(array_filter(self::TABLES, fn ($d) => $d !== 'up'));
    }

    public static function canPush(string $table): bool
    {
        return in_array($table, self::pushable(), true);
    }

    public static function canPull(string $table): bool
    {
        return in_array($table, self::pullable(), true);
    }
}
