@php
    $total30 = $daily->sum('total');
    $best = $daily->sortByDesc('total')->first();
@endphp
<section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 lg:col-span-2">
    <div class="mb-4 flex flex-wrap items-start justify-between gap-2">
        <div>
            <h2 class="font-semibold text-slate-900">Sales, last 30 days</h2>
            <p class="text-sm text-slate-500">KSh {{ number_format($total30) }} in total &middot; best day {{ $best['label'] ?? '-' }} (KSh {{ number_format($best['total'] ?? 0) }})</p>
        </div>
        <span class="inline-flex items-center gap-1.5 text-xs text-slate-500"><span class="h-2.5 w-2.5 rounded-sm bg-indigo-600"></span> Today</span>
    </div>
    <div class="h-64"
         x-data="barChart({
            labels: @js($daily->map(fn ($d) => \Illuminate\Support\Carbon::parse($d['date'])->format('j M'))->values()),
            values: @js($daily->pluck('total')->values()),
            extra: @js($daily->map(fn ($d) => $d['count'] . ' sales')->values()),
            money: true,
            highlight: {{ $daily->count() - 1 }},
         })">
        <canvas x-ref="canvas" aria-label="Daily sales for the last 30 days" role="img"></canvas>
    </div>
</section>
