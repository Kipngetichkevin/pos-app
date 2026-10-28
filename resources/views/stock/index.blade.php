@extends('layouts.app')

@section('title', 'Stock')

@section('content')
@include('partials.header')

<main class="mx-auto max-w-7xl px-4 py-6">
    @php
        $fmtQty = fn ($q) => rtrim(rtrim(number_format((float) $q, 3), '0'), '.');
    @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-2xl font-bold text-slate-900">Stock</h2>
        <div class="flex gap-2">
            <a href="{{ route('transfers.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 font-semibold text-slate-700 hover:bg-slate-50">Transfers</a>
            <a href="{{ route('stock-takes.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 font-semibold text-slate-700 hover:bg-slate-50">Stock take</a>
            <a href="{{ route('stock.receive') }}" class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">+ Receive delivery</a>
        </div>
    </div>

    @include('partials.flash')

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="text-xs uppercase text-slate-500">Stock value (cost)</div>
            <div class="mt-1 text-xl font-bold">{{ number_format($summary['cost_value'], 2) }}</div>
        </div>
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="text-xs uppercase text-slate-500">Retail value</div>
            <div class="mt-1 text-xl font-bold text-blue-700">{{ number_format($summary['retail_value'], 2) }}</div>
        </div>
        <a href="{{ route('stock.index', array_filter(['search' => $search, 'filter' => 'low'])) }}" class="rounded-2xl bg-white p-4 text-left shadow-sm ring-1 {{ $summary['low'] ? 'ring-amber-300' : 'ring-slate-200' }}">
            <div class="text-xs uppercase text-slate-500">Low stock</div>
            <div class="mt-1 text-xl font-bold {{ $summary['low'] ? 'text-amber-600' : '' }}">{{ $summary['low'] }}</div>
        </a>
        <a href="{{ route('stock.index', array_filter(['search' => $search, 'filter' => 'out'])) }}" class="rounded-2xl bg-white p-4 text-left shadow-sm ring-1 {{ $summary['out'] ? 'ring-red-300' : 'ring-slate-200' }}">
            <div class="text-xs uppercase text-slate-500">Out of stock</div>
            <div class="mt-1 text-xl font-bold {{ $summary['out'] ? 'text-red-600' : '' }}">{{ $summary['out'] }}</div>
        </a>
    </div>

    <form method="GET" action="{{ route('stock.index') }}" class="mb-4 flex flex-wrap gap-2">
        <input name="search" value="{{ $search }}" type="text" placeholder="Search name, SKU or barcode..."
               class="w-72 rounded-lg border border-slate-300 bg-white px-3 py-2">
        <select name="filter" onchange="this.form.submit()" class="rounded-lg border border-slate-300 bg-white px-3 py-2">
            <option value="all" @selected($filter === 'all')>All products</option>
            <option value="low" @selected($filter === 'low')>Low stock</option>
            <option value="out" @selected($filter === 'out')>Out of stock</option>
            <option value="inactive" @selected($filter === 'inactive')>Inactive products</option>
        </select>
    </form>

    @include('stock.partials.table')

    @if ($adjusting)
        @include('partials.modal', [
            'title' => 'Adjust stock',
            'close' => $listUrl,
            'body' => 'stock.partials.adjust',
            'width' => 'max-w-md',
        ])
    @endif

    @if ($historyProduct)
        @include('partials.modal', [
            'title' => 'Stock history: ' . $historyProduct->name,
            'close' => $listUrl,
            'body' => 'stock.partials.history',
            'width' => 'max-w-3xl',
        ])
    @endif
</main>
@endsection

@push('scripts')
<script>
    window.stockAdjust = (current, unit, initial) => ({
        unit,
        current,
        mode: initial.mode,
        qty: initial.qty,
        reason: initial.reason,
        fmt(v) {
            return Number(v).toLocaleString('en-US', { maximumFractionDigits: 3 });
        },
        get newStock() {
            if (this.qty === '' || this.qty === null || isNaN(Number(this.qty))) return null;
            const q = Number(this.qty);
            return this.mode === 'add' ? this.current + q : (this.mode === 'remove' ? this.current - q : q);
        },
    });
</script>
@endpush
