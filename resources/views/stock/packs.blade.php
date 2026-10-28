@extends('layouts.app')

@section('title', 'Pack sizes')

@section('content')
@include('partials.header')

<main class="mx-auto max-w-7xl px-4 py-6">
    @php $fq = fn ($q) => rtrim(rtrim(number_format((float) $q, 3), '0'), '.'); @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">{{ $product->name }}: pack sizes</h2>
            <p class="text-sm text-slate-500">
                Bulk in stock: <b>{{ $fq($bulk['qty']) }} {{ $unit }}</b>
                &middot; cost {{ number_format($bulk['avg'], 2) }} per {{ $unit }}
            </p>
        </div>
        <a href="{{ route('stock.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Back to Stock</a>
    </div>

    @include('partials.flash')

    <div class="grid gap-4 lg:grid-cols-2">
        @include('stock.partials.pack-sizes')
        @include('stock.partials.repack-form')
    </div>

    @include('stock.partials.repacks')
</main>
@endsection

@push('scripts')
<script>
    window.repackForm = (packs, bulkQty, unit, initial) => ({
        unit,
        packs,
        bulkQty,
        counts: initial.counts,
        bulkUsed: initial.bulkUsed,
        fq(v) {
            return Number(v).toLocaleString('en-US', { maximumFractionDigits: 3 });
        },
        // Same as PHP (int) on the typed value
        int(v) {
            const n = Math.trunc(parseFloat(v));
            return isNaN(n) ? 0 : n;
        },
        lineQty(id) {
            const pack = this.packs.find((p) => p.id === id);
            return pack ? this.int(this.counts[id]) * pack.size : 0;
        },
        get packed() {
            const sum = this.packs.reduce((total, p) => total + this.int(this.counts[p.id]) * p.size, 0);
            return Math.round(sum * 1000) / 1000;
        },
        get used() {
            const v = String(this.bulkUsed ?? '').trim();
            return v !== '' && !isNaN(Number(v)) ? Number(v) : this.packed;
        },
    });
</script>
@endpush
