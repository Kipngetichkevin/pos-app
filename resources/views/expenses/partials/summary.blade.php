<div class="h-fit rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <div class="text-sm text-slate-500">Total spent</div>
    <div class="mb-4 text-3xl font-bold">KSh {{ number_format($total, 2) }}</div>

    @foreach ($byCategory as $cat => $sum)
        @php $pct = $total > 0 ? round($sum / $total * 100) : 0; @endphp
        <div class="mb-2 text-sm">
            <div class="flex justify-between">
                <span>{{ \App\Models\Expense::CATEGORIES[$cat] ?? $cat }}</span>
                <span class="font-semibold">{{ number_format($sum, 2) }}</span>
            </div>
            <div class="mt-1 h-1.5 rounded-full bg-slate-100">
                <div class="h-1.5 rounded-full bg-blue-500" style="width: {{ $pct }}%"></div>
            </div>
        </div>
    @endforeach

    @if (! auth()->user()->isManager())
        <p class="mt-4 text-xs text-slate-400">You see only the expenses you recorded.</p>
    @endif
</div>
