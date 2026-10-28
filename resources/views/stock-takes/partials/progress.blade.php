@php $pct = $progress['total'] ? round($progress['counted'] / $progress['total'] * 100) : 0; @endphp
<div class="mb-4 grid gap-4 md:grid-cols-4">
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 md:col-span-2">
        <div class="flex justify-between text-sm">
            <span class="text-slate-500">Counted</span>
            <span class="font-semibold">{{ $progress['counted'] }} of {{ $progress['total'] }} products ({{ $pct }}%)</span>
        </div>
        <div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-blue-500" style="width: {{ $pct }}%"></div></div>
        @if ($take->isCounting())
            <div class="mt-3 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('stock-takes.complete', $take) }}"
                      onsubmit="return confirm('Finish the stock take? Stock for the {{ $progress['counted'] }} counted product(s) will be corrected. Products not counted stay as they are.')">
                    @csrf
                    <button class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">Finish &amp; update stock</button>
                </form>
                <form method="POST" action="{{ route('stock-takes.cancel', $take) }}" onsubmit="return confirm('Cancel this stock take? Nothing will change.')">
                    @csrf
                    <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancel stock take</button>
                </form>
            </div>
        @endif
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div class="text-sm text-slate-500">Shortage (missing)</div>
        <div class="text-2xl font-bold text-red-600">{{ number_format(abs($progress['shortage']), 2) }}</div>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div class="text-sm text-slate-500">Excess (extra found)</div>
        <div class="text-2xl font-bold text-green-700">{{ number_format($progress['excess'], 2) }}</div>
    </div>
</div>
