@extends('layouts.app')

@section('title', 'Sales')

@section('content')
@include('partials.header')

<main class="mx-auto max-w-7xl px-4 py-6">
    {{-- Header and tabs --}}
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-2xl font-bold text-slate-900">Sales &amp; M-Pesa</h2>
        <div class="flex rounded-lg bg-white p-1 ring-1 ring-slate-200">
            <a href="{{ route('sales.index', array_merge($filters, ['tab' => 'sales'])) }}"
               class="rounded-md px-4 py-1.5 text-sm font-semibold {{ $tab === 'sales' ? 'bg-blue-600 text-white' : 'text-slate-600' }}">Sales</a>
            <a href="{{ route('sales.index', array_merge($filters, ['tab' => 'mpesa'])) }}"
               class="rounded-md px-4 py-1.5 text-sm font-semibold {{ $tab === 'mpesa' ? 'bg-green-600 text-white' : 'text-slate-600' }}">M-Pesa</a>
        </div>
    </div>

    @include('sales.partials.filters')

    @include('partials.flash')

    @include('sales.partials.loss-items')

    @include('sales.partials.summary')

    @if ($tab === 'sales')
        @include('sales.partials.sales-table')
    @else
        @include('sales.partials.mpesa-summary')
        @include('sales.partials.unverified')
        @include('sales.partials.mpesa-table')
    @endif

    {{-- Sale details popup (?view=ID) --}}
    @if ($viewing)
        @include('sales.partials.sale-detail')
    @endif
</main>
@endsection
