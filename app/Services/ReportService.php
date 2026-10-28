<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * All the numbers behind the dashboard and reports, for one branch and a date range.
 * Voided sales are left out everywhere; refunds are subtracted on the day they were given.
 */
class ReportService
{
    public function __construct(
        protected string $branchId,
        protected CarbonInterface $from,
        protected CarbonInterface $to,
    ) {
        $this->from = Carbon::parse($from)->startOfDay();
        $this->to = Carbon::parse($to)->endOfDay();
    }

    public static function for(string $branchId, $from, $to): self
    {
        return new self($branchId, Carbon::parse($from), Carbon::parse($to));
    }

    protected function sales()
    {
        return DB::table('sales')
            ->where('sales.branch_id', $this->branchId)
            ->where('sales.status', '!=', 'voided')
            ->whereBetween('sales.sold_at', [$this->from, $this->to]);
    }

    protected function items()
    {
        return DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.branch_id', $this->branchId)
            ->where('sales.status', '!=', 'voided')
            ->whereBetween('sales.sold_at', [$this->from, $this->to]);
    }

    protected function returns()
    {
        return DB::table('sale_return_items')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
            ->where('sale_returns.branch_id', $this->branchId)
            ->whereBetween('sale_returns.returned_at', [$this->from, $this->to]);
    }

    /** Headline numbers: sales, profit, refunds, expenses and what is left. */
    public function summary(): array
    {
        $sales = $this->sales()->selectRaw('COUNT(*) as n, COALESCE(SUM(grand_total),0) as total, COALESCE(SUM(tax_total),0) as tax')->first();
        $items = $this->items()->selectRaw('COALESCE(SUM(sale_items.unit_cost * sale_items.quantity),0) as cost, COALESCE(SUM(sale_items.quantity),0) as qty')->first();
        $refunds = $this->returns()->selectRaw('COALESCE(SUM(sale_return_items.refund_amount),0) as refunded, COALESCE(SUM(sale_items.unit_cost * sale_return_items.quantity),0) as cost')->first();
        $expenses = (float) DB::table('expenses')->where('branch_id', $this->branchId)->whereNull('deleted_at')
            ->whereBetween('spent_at', [$this->from, $this->to])->sum('amount');

        $revenue = (float) $sales->total - (float) $refunds->refunded;
        $cost = (float) $items->cost - (float) $refunds->cost;
        $gross = $revenue - $cost;

        return [
            'count' => (int) $sales->n,
            'sales' => (float) $sales->total,
            'refunds' => (float) $refunds->refunded,
            'revenue' => round($revenue, 2),
            'tax' => (float) $sales->tax,
            'cost' => round($cost, 2),
            'gross_profit' => round($gross, 2),
            'margin' => $revenue > 0 ? round($gross / $revenue * 100, 1) : 0,
            'expenses' => round($expenses, 2),
            'net_profit' => round($gross - $expenses, 2),
            'avg_basket' => $sales->n > 0 ? round((float) $sales->total / $sales->n, 2) : 0,
            'items_sold' => (float) $items->qty,
        ];
    }

    /** Summary of the range, stopping at a given moment (e.g. yesterday up to this time of day). */
    public function summaryUntil(CarbonInterface $until): array
    {
        $copy = clone $this;
        $copy->to = Carbon::parse($until);

        return $copy->summary();
    }

    /** One row per day in the range, including days with no sales. */
    public function daily(): Collection
    {
        $rows = $this->sales()->selectRaw('DATE(sold_at) as d, COUNT(*) as n, SUM(grand_total) as total')
            ->groupBy('d')->get()->keyBy('d');
        $profit = $this->items()->selectRaw('DATE(sales.sold_at) as d, SUM(sale_items.line_total - sale_items.unit_cost * sale_items.quantity) as p')
            ->groupBy('d')->pluck('p', 'd');

        $days = collect();

        for ($day = $this->from->copy(); $day->lte($this->to); $day->addDay()) {
            $key = $day->toDateString();
            $days->push([
                'date' => $key,
                'label' => $day->format('D j M'),
                'count' => (int) ($rows[$key]->n ?? 0),
                'total' => round((float) ($rows[$key]->total ?? 0), 2),
                'profit' => round((float) ($profit[$key] ?? 0), 2),
            ]);
        }

        return $days;
    }

    /** Sales by hour of the day (0-23), for staffing and opening hours. */
    public function hourly(): Collection
    {
        $rows = $this->sales()->selectRaw('HOUR(sold_at) as h, COUNT(*) as n, SUM(grand_total) as total')->groupBy('h')->get()->keyBy('h');

        return collect(range(0, 23))->map(fn ($h) => [
            'hour' => $h,
            'label' => sprintf('%02d:00', $h),
            'count' => (int) ($rows[$h]->n ?? 0),
            'total' => round((float) ($rows[$h]->total ?? 0), 2),
        ]);
    }

    /** Money in by payment method (cash is net of change). Fixed order: cash, M-Pesa, credit, other. */
    public function payments(): array
    {
        $by = DB::table('sale_payments')->joinSub($this->sales()->select('sales.id'), 's', 's.id', '=', 'sale_payments.sale_id')
            ->selectRaw('method, SUM(amount) as total')->groupBy('method')->pluck('total', 'method');

        return [
            'cash' => (float) ($by['cash'] ?? 0),
            'mpesa' => (float) ($by['mpesa'] ?? 0),
            'credit' => (float) ($by['credit'] ?? 0),
            'other' => (float) $by->except(['cash', 'mpesa', 'credit'])->sum(),
        ];
    }

    /** Best sellers, by money or by quantity. */
    public function topProducts(int $limit = 10, string $by = 'revenue'): Collection
    {
        return $this->items()
            ->selectRaw('sale_items.product_id, MAX(sale_items.product_name) as name, SUM(sale_items.quantity) as qty, SUM(sale_items.line_total) as revenue, SUM(sale_items.line_total - sale_items.unit_cost * sale_items.quantity) as profit')
            ->groupBy('sale_items.product_id')
            ->orderByDesc($by === 'qty' ? 'qty' : 'revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'product_id' => $r->product_id,
                'name' => $r->name,
                'qty' => (float) $r->qty,
                'revenue' => round((float) $r->revenue, 2),
                'profit' => round((float) $r->profit, 2),
                'margin' => (float) $r->revenue > 0 ? round((float) $r->profit / (float) $r->revenue * 100, 1) : 0,
            ]);
    }

    public function byCategory(): Collection
    {
        return $this->items()
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->selectRaw("COALESCE(categories.name, 'Uncategorised') as name, SUM(sale_items.line_total) as revenue, SUM(sale_items.line_total - sale_items.unit_cost * sale_items.quantity) as profit")
            ->groupBy('name')->orderByDesc('revenue')->get()
            ->map(fn ($r) => [
                'name' => $r->name,
                'revenue' => round((float) $r->revenue, 2),
                'profit' => round((float) $r->profit, 2),
                'margin' => (float) $r->revenue > 0 ? round((float) $r->profit / (float) $r->revenue * 100, 1) : 0,
            ]);
    }

    public function byCashier(): Collection
    {
        return $this->sales()->join('users', 'users.id', '=', 'sales.user_id')
            ->selectRaw('users.name, COUNT(*) as n, SUM(sales.grand_total) as total')
            ->groupBy('users.name')->orderByDesc('total')->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->n, 'total' => round((float) $r->total, 2), 'avg' => $r->n ? round((float) $r->total / $r->n, 2) : 0]);
    }

    /** VAT for the KRA return: sales and refunds by tax code (A exempt, B 16%, C zero, D non-VAT, E 8%). */
    public function vat(): Collection
    {
        $rates = DB::table('tax_rates')->orderBy('code')->get()->keyBy('code');
        $productTax = DB::table('products')->join('tax_rates', 'tax_rates.id', '=', 'products.tax_rate_id')->pluck('tax_rates.code', 'products.id');

        $sold = $this->items()->selectRaw('sale_items.product_id, SUM(sale_items.line_total) as gross, SUM(sale_items.tax_amount) as tax')
            ->groupBy('sale_items.product_id')->get();
        $back = $this->returns()->selectRaw('sale_items.product_id, SUM(sale_return_items.refund_amount) as gross, SUM(sale_items.tax_amount / sale_items.quantity * sale_return_items.quantity) as tax')
            ->groupBy('sale_items.product_id')->get();

        $out = $rates->map(fn ($r) => ['code' => $r->code, 'name' => $r->name, 'rate' => (float) $r->rate, 'gross' => 0.0, 'tax' => 0.0, 'refund_gross' => 0.0, 'refund_tax' => 0.0]);

        foreach ($sold as $row) {
            $code = $productTax[$row->product_id] ?? 'B';
            $line = $out[$code];
            $line['gross'] += (float) $row->gross;
            $line['tax'] += (float) $row->tax;
            $out[$code] = $line;
        }

        foreach ($back as $row) {
            $code = $productTax[$row->product_id] ?? 'B';
            $line = $out[$code];
            $line['refund_gross'] += (float) $row->gross;
            $line['refund_tax'] += (float) $row->tax;
            $out[$code] = $line;
        }

        return $out->map(function ($l) {
            $gross = $l['gross'] - $l['refund_gross'];
            $tax = $l['tax'] - $l['refund_tax'];

            return $l + ['net_gross' => round($gross, 2), 'net_tax' => round($tax, 2), 'net_ex_vat' => round($gross - $tax, 2)];
        })->values();
    }

    /** Stock that has not sold in the range: money tied up on the shelf. */
    public function slowMovers(int $limit = 20): Collection
    {
        $sold = $this->items()->select('sale_items.product_id')->distinct();

        return DB::table('stock_levels')->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->where('stock_levels.branch_id', $this->branchId)
            ->where('stock_levels.quantity', '>', 0)
            ->where('products.is_active', true)
            ->whereNotIn('products.id', $sold)
            ->selectRaw('products.id, products.name, stock_levels.quantity as qty, stock_levels.quantity * stock_levels.average_cost as value')
            ->orderByDesc('value')->limit($limit)->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'qty' => (float) $r->qty, 'value' => round((float) $r->value, 2)]);
    }

    public function expensesByCategory(): Collection
    {
        return DB::table('expenses')->where('branch_id', $this->branchId)->whereNull('deleted_at')
            ->whereBetween('spent_at', [$this->from, $this->to])
            ->selectRaw('category, SUM(amount) as total')->groupBy('category')->orderByDesc('total')
            ->pluck('total', 'category')->map(fn ($v) => round((float) $v, 2));
    }
}
