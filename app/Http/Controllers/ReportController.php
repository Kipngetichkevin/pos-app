<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Shift;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public const TABS = [
        'overview' => 'Overview',
        'products' => 'Products',
        'profit' => 'Profit & loss',
        'vat' => 'VAT (KRA)',
        'staff' => 'Staff',
    ];

    public function index(Request $request)
    {
        [$from, $to, $preset] = $this->range($request);
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'overview';
        $report = ReportService::for($request->user()->branch_id, $from, $to);

        $data = match ($tab) {
            'products' => [
                'top' => $report->topProducts(25, $request->query('by') === 'qty' ? 'qty' : 'revenue'),
                'categories' => $report->byCategory(),
                'slow' => $report->slowMovers(15),
            ],
            'profit' => [
                'summary' => $report->summary(),
                'expenses' => $report->expensesByCategory(),
            ],
            'vat' => ['vat' => $report->vat()],
            'staff' => [
                'cashiers' => $report->byCashier(),
                'shifts' => Shift::with(['user', 'till'])->where('branch_id', $request->user()->branch_id)
                    ->where('status', 'closed')->whereBetween('closed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                    ->latest('closed_at')->get(),
            ],
            default => [
                'summary' => $report->summary(),
                'daily' => $report->daily(),
                'hourly' => $report->hourly(),
                'payments' => $report->payments(),
            ],
        };

        return view('reports.index', $data + [
            'tab' => $tab,
            'from' => $from,
            'to' => $to,
            'preset' => $preset,
            'presets' => $this->presets(),
        ]);
    }

    /** The table on the current tab as a spreadsheet-friendly CSV. */
    public function export(Request $request)
    {
        [$from, $to] = $this->range($request);
        $report = ReportService::for($request->user()->branch_id, $from, $to);
        $tab = $request->query('tab', 'overview');

        [$headers, $rows] = match ($tab) {
            'products' => [['Product', 'Quantity', 'Sales (KSh)', 'Gross profit (KSh)', 'Margin %'],
                $report->topProducts(1000)->map(fn ($r) => [$r['name'], $r['qty'], $r['revenue'], $r['profit'], $r['margin']])],
            'vat' => [['Tax code', 'Name', 'Rate %', 'Sales incl. VAT', 'Refunds incl. VAT', 'Net incl. VAT', 'VAT', 'Net excl. VAT'],
                $report->vat()->map(fn ($r) => [$r['code'], $r['name'], $r['rate'], round($r['gross'], 2), round($r['refund_gross'], 2), $r['net_gross'], $r['net_tax'], $r['net_ex_vat']])],
            'staff' => [['Cashier', 'Sales', 'Total (KSh)', 'Average sale (KSh)'],
                $report->byCashier()->map(fn ($r) => [$r['name'], $r['count'], $r['total'], $r['avg']])],
            default => [['Date', 'Sales', 'Total (KSh)', 'Gross profit (KSh)'],
                $report->daily()->map(fn ($r) => [$r['date'], $r['count'], $r['total'], $r['profit']])],
        };

        $name = "report-{$tab}-{$from->toDateString()}-to-{$to->toDateString()}.csv";

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    protected function presets(): array
    {
        return [
            'today' => ['Today', today(), today()],
            'yesterday' => ['Yesterday', today()->subDay(), today()->subDay()],
            'week' => ['This week', today()->startOfWeek(), today()],
            'month' => ['This month', today()->startOfMonth(), today()],
            'last_month' => ['Last month', today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            '30days' => ['Last 30 days', today()->subDays(29), today()],
        ];
    }

    protected function range(Request $request): array
    {
        $presets = $this->presets();
        $preset = $request->query('range');

        if ($preset && isset($presets[$preset])) {
            return [$presets[$preset][1], $presets[$preset][2], $preset];
        }

        if ($request->filled('from') || $request->filled('to')) {
            $from = Carbon::parse($request->query('from', today()->toDateString()));
            $to = Carbon::parse($request->query('to', today()->toDateString()));

            return [$from->min($to), $to->max($from), 'custom'];
        }

        return [$presets['month'][1], $presets['month'][2], 'month'];
    }
}
