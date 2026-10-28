<?php

namespace App\Http\Controllers;

use App\Models\Sale;

class ReceiptController extends Controller
{
    public function show(Sale $sale)
    {
        $sale->load(['items', 'payments', 'branch', 'cashier', 'customer', 'etimsInvoice']);

        return view('receipts.show', compact('sale'));
    }
}
