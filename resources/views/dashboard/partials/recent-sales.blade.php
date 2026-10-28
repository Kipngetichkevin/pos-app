<section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <div class="mb-4 flex items-start justify-between">
        <div>
            <h2 class="font-semibold text-slate-900">Latest sales</h2>
            <p class="text-sm text-slate-500">Most recent first</p>
        </div>
        <a href="{{ route('sales.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-700">View all</a>
    </div>

    <ul class="divide-y divide-slate-100">
        @forelse ($recent as $sale)
            <li>
                <a href="{{ route('receipts.show', $sale) }}" target="_blank" class="-mx-2 flex items-center justify-between rounded-lg px-2 py-2.5 hover:bg-slate-50">
                    <span>
                        <span class="block font-mono text-xs font-semibold text-slate-700">{{ $sale->receipt_no }}</span>
                        <span class="block text-xs text-slate-400">{{ $sale->sold_at->diffForHumans() }} &middot; {{ $sale->cashier?->name }}</span>
                    </span>
                    <span class="tabular text-sm font-semibold {{ $sale->status === 'voided' ? 'text-slate-400 line-through' : 'text-slate-900' }}">{{ number_format($sale->grand_total) }}</span>
                </a>
            </li>
        @empty
            <li class="py-6 text-center text-sm text-slate-400">No sales yet.</li>
        @endforelse
    </ul>
</section>
