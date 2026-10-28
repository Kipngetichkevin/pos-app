<?php

namespace App\Http\Controllers;

use App\Models\MpesaTransaction;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        // Filters come from the query string (tab, from, to, search, cashier, mpesaFilter)
        $tab = $request->query('tab') === 'mpesa' ? 'mpesa' : 'sales';
        $fromInput = (string) ($request->query('from') ?? now()->toDateString());
        $toInput = (string) ($request->query('to') ?? now()->toDateString());
        $search = (string) $request->query('search', '');
        $cashier = (string) $request->query('cashier', '');
        $mpesaFilter = (string) ($request->query('mpesaFilter') ?? 'all');

        [$from, $to] = $this->range($fromInput, $toInput);
        $term = trim($search);

        // Summary for the selected dates (voided sales excluded)
        $inRange = Sale::query()
            ->whereBetween('sold_at', [$from, $to])
            ->where('status', '!=', 'voided')
            ->when($cashier, fn ($q) => $q->where('user_id', $cashier));

        $saleIds = (clone $inRange)->select('id');

        $summary = [
            'count' => (clone $inRange)->count(),
            'total' => (float) (clone $inRange)->sum('grand_total'),
            'tax' => (float) (clone $inRange)->sum('tax_total'),
            'cash' => (float) SalePayment::whereIn('sale_id', $saleIds)->where('method', 'cash')->sum('amount'),
            'mpesa' => (float) SalePayment::whereIn('sale_id', $saleIds)->where('method', 'mpesa')->sum('amount'),
            'profit' => (float) SaleItem::whereIn('sale_id', $saleIds)
                ->selectRaw('COALESCE(SUM(line_total - unit_cost * quantity), 0) as profit')
                ->value('profit'),
        ];

        // Items sold below cost in the selected dates
        $lossItems = SaleItem::query()
            ->whereIn('sale_id', $saleIds)
            ->whereRaw('line_total < unit_cost * quantity')
            ->selectRaw('product_name, unit_name, COUNT(*) as times, SUM(quantity) as qty, SUM(unit_cost * quantity - line_total) as loss')
            ->groupBy('product_name', 'unit_name')
            ->orderByDesc('loss')
            ->get();

        // Refunds in the selected dates reduce cash and profit
        $returned = DB::table('sale_return_items as ri')
            ->join('sale_returns as r', 'r.id', '=', 'ri.sale_return_id')
            ->join('sale_items as si', 'si.id', '=', 'ri.sale_item_id')
            ->whereBetween('r.returned_at', [$from, $to]);
        $summary['refunds'] = (float) (clone $returned)->sum('ri.refund_amount');
        $summary['profit'] -= (float) (clone $returned)->selectRaw('COALESCE(SUM(ri.refund_amount - si.unit_cost * ri.quantity), 0) as p')->value('p');
        $summary['cash'] -= (float) DB::table('sale_returns')->whereBetween('returned_at', [$from, $to])->where('refund_method', 'cash')->sum('total_refunded');

        $sales = null;
        $mpesa = null;
        $mpesaSummary = null;
        $unverified = collect();

        if ($tab === 'sales') {
            $sales = Sale::query()
                ->with(['cashier', 'payments'])
                ->withCount('items')
                ->when($term === '', fn ($q) => $q->whereBetween('sold_at', [$from, $to]))
                ->when($cashier, fn ($q) => $q->where('user_id', $cashier))
                ->when($term !== '', function ($q) use ($term) {
                    $q->where(function ($q) use ($term) {
                        $q->where('receipt_no', 'like', "%{$term}%")
                            ->orWhereHas('payments', fn ($p) => $p->where('reference', 'like', "%{$term}%"));
                    });
                })
                ->latest('sold_at')
                ->paginate(25)
                ->withQueryString();
        } else {
            $mpesa = MpesaTransaction::query()
                ->with('payments.sale')
                ->when($term === '', fn ($q) => $q->whereBetween('created_at', [$from, $to]))
                ->when($term !== '', function ($q) use ($term) {
                    $q->where(function ($q) use ($term) {
                        $q->where('mpesa_receipt', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%")
                            ->orWhere('first_name', 'like', "%{$term}%");
                    });
                })
                ->when($mpesaFilter === 'unattached', fn ($q) => $q->where('status', 'success')->whereDoesntHave('payments'))
                ->when(in_array($mpesaFilter, ['success', 'pending', 'failed', 'cancelled']), fn ($q) => $q->where('status', $mpesaFilter))
                ->latest()
                ->paginate(25)
                ->withQueryString();

            $received = MpesaTransaction::whereBetween('created_at', [$from, $to])->where('status', 'success');

            $mpesaSummary = [
                'received' => (float) (clone $received)->sum('amount'),
                'count' => (clone $received)->count(),
                'unattached' => (clone $received)->whereDoesntHave('payments')->count(),
                'unattachedAmount' => (float) (clone $received)->whereDoesntHave('payments')->sum('amount'),
            ];

            $unverified = SalePayment::with('sale')
                ->where('method', 'mpesa')
                ->where('status', 'pending')
                ->latest()
                ->limit(20)
                ->get();
        }

        // ?view=ID opens the sale details popup
        $viewing = $request->query('view')
            ? Sale::with(['items', 'payments', 'cashier', 'till'])->find($request->query('view'))
            : null;

        return view('sales.index', [
            'tab' => $tab,
            'from' => $fromInput,
            'to' => $toInput,
            'search' => $search,
            'cashier' => $cashier,
            'mpesaFilter' => $mpesaFilter,
            // Carried over by the tab, quick-range and "View" links (page is reset)
            'filters' => [
                'tab' => $tab,
                'from' => $fromInput,
                'to' => $toInput,
                'search' => $search,
                'cashier' => $cashier,
                'mpesaFilter' => $mpesaFilter,
            ],
            'ranges' => $this->quickRanges(),
            'summary' => $summary,
            'lossItems' => $lossItems,
            'sales' => $sales,
            'mpesa' => $mpesa,
            'mpesaSummary' => $mpesaSummary,
            'unverified' => $unverified,
            'cashiers' => User::orderBy('name')->get(['id', 'name']),
            'viewing' => $viewing,
        ]);
    }

    /** Try to match an unverified M-Pesa payment against records Safaricom has sent */
    public function verifyPayment(SalePayment $payment)
    {
        if ($payment->method !== 'mpesa' || $payment->status !== 'pending') {
            return back();
        }

        $tx = MpesaTransaction::where('mpesa_receipt', $payment->reference)
            ->where('status', 'success')
            ->whereDoesntHave('payments')
            ->first();

        if (! $tx) {
            return back()->with('warning', "Code {$payment->reference} is still not in the M-Pesa records.");
        }

        if (abs((float) $tx->amount - (float) $payment->amount) > 0.009) {
            return back()->with('warning', "Code {$payment->reference} was for KSh " . number_format((float) $tx->amount, 2)
                . ', but the sale recorded KSh ' . number_format((float) $payment->amount, 2) . '.');
        }

        $payment->update(['status' => 'confirmed', 'mpesa_transaction_id' => $tx->id]);

        return back()->with('status', "Payment {$payment->reference} is now verified.");
    }

    protected function range(string $from, string $to): array
    {
        $from = $from ? Carbon::parse($from)->startOfDay() : now()->startOfDay();
        $to = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }

    /** Quick date links: label => [from, to] */
    protected function quickRanges(): array
    {
        return [
            'Today' => [now()->toDateString(), now()->toDateString()],
            'Yesterday' => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
            'This week' => [now()->startOfWeek()->toDateString(), now()->toDateString()],
            'This month' => [now()->startOfMonth()->toDateString(), now()->toDateString()],
        ];
    }
}
