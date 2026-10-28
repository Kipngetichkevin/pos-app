<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\EtimsInvoice;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Shift;
use App\Models\StockTransfer;
use App\Services\ReportService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $shift = Shift::with('till')->where('user_id', $user->id)->where('status', 'open')->latest('opened_at')->first();

        if (! $user->isManager()) {
            return view('dashboard.cashier', [
                'shift' => $shift,
                'summary' => $shift?->summary(),
                'recent' => $shift ? Sale::where('shift_id', $shift->id)->latest('sold_at')->limit(8)->get() : collect(),
            ]);
        }

        $branchId = $user->branch_id;
        $today = ReportService::for($branchId, today(), now());
        // Yesterday up to the same time of day, so the comparison is fair in the middle of the day
        $yesterday = ReportService::for($branchId, today()->subDay(), today()->subDay())->summaryUntil(now()->subDay());
        $month = ReportService::for($branchId, today()->subDays(29), now());
        $week = ReportService::for($branchId, today()->subDays(6), now());

        return view('dashboard.manager', [
            'greeting' => match (true) { now()->hour < 12 => 'Good morning', now()->hour < 17 => 'Good afternoon', default => 'Good evening' },
            'today' => $today->summary(),
            'yesterday' => $yesterday,
            'payments' => $today->payments(),
            'daily' => $month->daily(),
            'top' => $week->topProducts(6),
            'debt' => (float) Customer::where('balance', '>', 0)->sum('balance'),
            'debtors' => Customer::where('balance', '>', 0)->count(),
            'recent' => Sale::with('cashier')->where('branch_id', $branchId)->latest('sold_at')->limit(7)->get(),
            'alerts' => $this->alerts($branchId),
        ]);
    }

    /** Things a manager should act on, each linking to where it is fixed. */
    protected function alerts(string $branchId): array
    {
        $stockSql = 'COALESCE((SELECT quantity FROM stock_levels WHERE stock_levels.product_id = products.id AND stock_levels.branch_id = ? LIMIT 1), 0)';

        $alerts = [
            [
                'count' => Product::where('is_active', true)->where('track_stock', true)->whereRaw("{$stockSql} <= 0", [$branchId])->count(),
                'label' => 'out of stock', 'tone' => 'red', 'icon' => 'cube', 'url' => route('stock.index', ['filter' => 'out']),
            ],
            [
                'count' => Product::where('is_active', true)->where('track_stock', true)->whereRaw("{$stockSql} > 0 AND {$stockSql} <= products.reorder_level", [$branchId, $branchId])->count(),
                'label' => 'running low', 'tone' => 'amber', 'icon' => 'exclamation-triangle', 'url' => route('stock.index', ['filter' => 'low']),
            ],
            [
                'count' => SalePayment::where('method', 'mpesa')->where('status', 'pending')->count(),
                'label' => 'unverified M-Pesa payments', 'tone' => 'amber', 'icon' => 'device-phone-mobile', 'url' => route('sales.index', ['tab' => 'mpesa']),
            ],
            [
                'count' => EtimsInvoice::where('branch_id', $branchId)->where('status', 'failed')->count(),
                'label' => 'invoices not accepted by KRA', 'tone' => 'red', 'icon' => 'document-check', 'url' => route('etims.index'),
            ],
            [
                'count' => StockTransfer::where('to_branch_id', $branchId)->where('status', 'sent')->count(),
                'label' => 'transfers waiting to be received', 'tone' => 'indigo', 'icon' => 'truck', 'url' => route('transfers.index'),
            ],
            [
                'count' => Shift::where('branch_id', $branchId)->where('status', 'open')->where('opened_at', '<', today())->count(),
                'label' => 'shifts left open from earlier days', 'tone' => 'amber', 'icon' => 'clock', 'url' => route('shifts.index'),
            ],
        ];

        return array_values(array_filter($alerts, fn ($a) => $a['count'] > 0));
    }
}
