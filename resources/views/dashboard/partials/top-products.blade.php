@php $max = $top->max('revenue') ?: 1; @endphp
<section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <h2 class="font-semibold text-slate-900">Best sellers this week</h2>
    <p class="mb-4 text-sm text-slate-500">By money taken, last 7 days</p>

    <ol class="space-y-3">
        @forelse ($top as $i => $p)
            <li title="{{ $p['name'] }}: KSh {{ number_format($p['revenue']) }} · {{ rtrim(rtrim(number_format($p['qty'], 3), '0'), '.') }} sold · {{ $p['margin'] }}% margin">
                <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                    <span class="truncate text-slate-700"><span class="mr-1.5 text-slate-400">{{ $i + 1 }}</span>{{ $p['name'] }}</span>
                    <span class="tabular shrink-0 font-semibold text-slate-900">{{ number_format($p['revenue']) }}</span>
                </div>
                <div class="h-1.5 rounded-full bg-slate-100">
                    <div class="h-1.5 rounded-full bg-indigo-500" style="width: {{ $p['revenue'] / $max * 100 }}%"></div>
                </div>
            </li>
        @empty
            <li class="py-6 text-center text-sm text-slate-400">No sales yet this week.</li>
        @endforelse
    </ol>
</section>
