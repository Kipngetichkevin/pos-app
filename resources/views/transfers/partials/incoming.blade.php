<div class="mb-4 rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-200">
    <div class="mb-2 font-semibold text-amber-900">Stock on its way to this branch</div>
    @foreach ($incoming as $t)
        <a href="{{ route('transfers.show', $t) }}" class="mb-1 flex items-center justify-between rounded-lg bg-white px-3 py-2 text-sm hover:bg-amber-100">
            <span><span class="font-mono font-semibold">{{ $t->transfer_no }}</span> from {{ $t->fromBranch?->name }} &middot; {{ $t->items_count }} product(s) &middot; sent {{ $t->sent_at->diffForHumans() }}</span>
            <span class="font-semibold text-amber-700">Confirm arrival &rarr;</span>
        </a>
    @endforeach
</div>
