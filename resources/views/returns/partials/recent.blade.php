<div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
    <div class="mb-2 text-xs font-semibold uppercase text-slate-500">This shift's sales</div>
    @if (! $shift)
        <p class="text-sm text-slate-500">You have no open shift.</p>
    @endif
    @foreach ($recentSales as $rs)
        <a href="{{ route('returns', ['sale' => $rs->id]) }}"
           class="mb-1 flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm hover:bg-blue-50 {{ $sale?->id === $rs->id ? 'bg-blue-50 ring-1 ring-blue-200' : '' }}">
            <span>
                <span class="font-mono text-xs font-semibold">{{ $rs->receipt_no }}</span>
                <span class="block text-xs text-slate-500">{{ $rs->sold_at->format('H:i') }} &middot; {{ str_replace('_', ' ', $rs->status) }}</span>
            </span>
            <span class="font-semibold {{ $rs->status === 'voided' ? 'line-through opacity-50' : '' }}">{{ number_format($rs->grand_total, 2) }}</span>
        </a>
    @endforeach
</div>
