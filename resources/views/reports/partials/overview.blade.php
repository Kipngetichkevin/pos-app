@php
    $days = $daily->count();
    $busiest = $hourly->sortByDesc('total')->first();
    $openHours = $hourly->filter(fn ($h) => $h['count'] > 0);
@endphp

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @include('partials.stat', ['label' => 'Sales (after refunds)', 'icon' => 'banknotes', 'value' => 'KSh ' . number_format($summary['revenue']),
        'sub' => number_format($summary['count']) . ' sales' . ($summary['refunds'] > 0 ? ' · ' . number_format($summary['refunds']) . ' refunded' : '')])
    @include('partials.stat', ['label' => 'Gross profit', 'icon' => 'arrow-trending-up', 'value' => 'KSh ' . number_format($summary['gross_profit']),
        'sub' => $summary['margin'] . '% margin'])
    @include('partials.stat', ['label' => 'Net profit (after expenses)', 'icon' => 'receipt-percent', 'value' => 'KSh ' . number_format($summary['net_profit']),
        'sub' => 'Expenses KSh ' . number_format($summary['expenses'])])
    @include('partials.stat', ['label' => 'Average sale', 'icon' => 'shopping-cart', 'value' => 'KSh ' . number_format($summary['avg_basket']),
        'sub' => $days > 1 ? number_format($summary['count'] / max($days, 1), 1) . ' sales a day' : null])
</div>

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 lg:col-span-2">
        <h2 class="font-semibold text-slate-900">Sales by day</h2>
        <p class="mb-4 text-sm text-slate-500">Hover a bar for the day's total and number of sales</p>
        <div class="h-64" x-data="barChart({
                labels: @js($daily->map(fn ($d) => \Illuminate\Support\Carbon::parse($d['date'])->format($days > 14 ? 'j M' : 'D j'))->values()),
                values: @js($daily->pluck('total')->values()),
                extra: @js($daily->map(fn ($d) => $d['count'] . ' sales · profit ' . number_format($d['profit']))->values()),
                money: true,
             })">
            <canvas x-ref="canvas" role="img" aria-label="Sales by day"></canvas>
        </div>
    </section>

    @include('dashboard.partials.payment-mix', ['mixTitle' => 'How customers paid'])
</div>

<section class="mt-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <h2 class="font-semibold text-slate-900">Busiest hours</h2>
    <p class="mb-4 text-sm text-slate-500">
        @if ($busiest && $busiest['total'] > 0)
            Most money comes in around {{ $busiest['label'] }}. Plan staff and restocking around it.
        @else
            No sales in this period.
        @endif
    </p>
    @php $hours = $openHours->isEmpty() ? $hourly : $hourly->whereBetween('hour', [$openHours->min('hour'), $openHours->max('hour')]); @endphp
    <div class="h-52" x-data="barChart({
            labels: @js($hours->pluck('label')->values()),
            values: @js($hours->pluck('total')->values()),
            extra: @js($hours->map(fn ($h) => $h['count'] . ' sales')->values()),
            money: true,
         })">
        <canvas x-ref="canvas" role="img" aria-label="Sales by hour of day"></canvas>
    </div>
</section>
