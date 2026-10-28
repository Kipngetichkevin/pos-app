@php $max = $cashiers->max('total') ?: 1; @endphp
<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h2 class="font-semibold text-slate-900">Sales by cashier</h2>
        <p class="mb-4 text-sm text-slate-500">Who served the most</p>
        <div class="space-y-4">
            @forelse ($cashiers as $c)
                <div>
                    <div class="mb-1 flex items-baseline justify-between text-sm">
                        <span class="font-medium text-slate-800">{{ $c['name'] }}</span>
                        <span class="tabular"><b class="text-slate-900">KSh {{ number_format($c['total']) }}</b> <span class="text-xs text-slate-400">{{ $c['count'] }} sales · avg {{ number_format($c['avg']) }}</span></span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-indigo-500" style="width: {{ $c['total'] / $max * 100 }}%"></div></div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-slate-400">No sales in this period.</p>
            @endforelse
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="px-5 pt-5">
            <h2 class="font-semibold text-slate-900">Cash-ups</h2>
            <p class="text-sm text-slate-500">Shifts closed in this period: {{ $shifts->where('variance', '<', 0)->count() }} short, {{ $shifts->where('variance', '>', 0)->count() }} over</p>
        </div>
        <table class="mt-4 w-full text-left text-sm">
            <thead class="border-y border-slate-100 bg-slate-50 text-xs uppercase text-slate-500">
                <tr><th class="px-5 py-2.5">Shift</th><th class="px-3 py-2.5 text-right">Expected</th><th class="px-5 py-2.5 text-right">Difference</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($shifts as $sh)
                    <tr>
                        <td class="px-5 py-2.5">
                            <a href="{{ route('shifts.report', $sh) }}" class="font-medium text-slate-800 hover:text-indigo-600">{{ $sh->user?->name }}</a>
                            <span class="block text-xs text-slate-400">{{ $sh->closed_at->format('D j M, H:i') }} · till {{ $sh->till?->code }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-right">{{ number_format($sh->expected_cash) }}</td>
                        <td class="px-5 py-2.5 text-right font-semibold {{ (float) $sh->variance < 0 ? 'text-rose-600' : ((float) $sh->variance > 0 ? 'text-amber-600' : 'text-emerald-600') }}">
                            {{ (float) $sh->variance == 0 ? 'Balanced' : ((float) $sh->variance > 0 ? '+' : '') . number_format($sh->variance) }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-8 text-center text-slate-400">No shifts closed in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
