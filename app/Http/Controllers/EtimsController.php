<?php

namespace App\Http\Controllers;

use App\Models\EtimsInvoice;
use App\Models\Product;
use App\Services\EtimsService;
use Illuminate\Http\Request;

class EtimsController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->user()->branch_id;
        $status = (string) $request->query('status', 'failed');

        $base = EtimsInvoice::where('branch_id', $branchId);

        return view('etims.index', [
            'enabled' => EtimsService::enabled(),
            'driver' => config('etims.driver'),
            'pin' => config('etims.pin'),
            'bhfId' => config('etims.branch_id'),
            'status' => $status,
            'counts' => (clone $base)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
            'invoices' => (clone $base)->with(['sale', 'saleReturn'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest()->paginate(30)->withQueryString(),
            'unclassified' => Product::where('is_active', true)->whereNull('etims_class_code')->count(),
        ]);
    }

    public function retry(Request $request, EtimsInvoice $invoice, EtimsService $etims)
    {
        abort_unless($invoice->branch_id === $request->user()->branch_id, 404);

        $etims->retry($invoice);
        $sent = $etims->send($invoice->fresh());

        return back()->with($sent ? 'status' : 'warning', $sent
            ? "Invoice for {$invoice->sale?->receipt_no} accepted by KRA."
            : 'Still not accepted: ' . $invoice->fresh()->last_error);
    }

    public function retryAll(Request $request, EtimsService $etims)
    {
        EtimsInvoice::where('branch_id', $request->user()->branch_id)
            ->whereIn('status', ['queued', 'failed'])
            ->update(['next_attempt_at' => now(), 'attempts' => 0]);

        $done = $etims->sendDue(200);

        return back()->with('status', "Sent {$done['sent']} invoice(s) to KRA. {$done['failed']} still failing.");
    }
}
