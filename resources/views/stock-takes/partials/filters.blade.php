<form method="GET" action="{{ route('stock-takes.show', $take) }}" class="mb-3 flex flex-wrap items-center gap-2">
    <input name="search" value="{{ $search }}" type="text" placeholder="Scan barcode or search name, then Enter" autofocus
           class="w-72 rounded-lg border border-slate-300 bg-white px-3 py-2">
    @foreach (['uncounted' => 'Not counted', 'counted' => 'Counted', 'variance' => 'Differences', 'all' => 'All'] as $key => $label)
        <a href="{{ route('stock-takes.show', ['stockTake' => $take, 'filter' => $key, 'search' => $search ?: null]) }}"
           class="rounded-full px-3 py-1 text-sm font-medium {{ $filter === $key ? 'bg-blue-600 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50' }}">
            {{ $label }}{{ $key === 'variance' ? ' (' . $progress['with_variance'] . ')' : '' }}
        </a>
    @endforeach
    <input type="hidden" name="filter" value="{{ $filter }}">
</form>
