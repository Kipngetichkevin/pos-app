@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@include('partials.header')

@php
    $change = fn ($now, $before) => $before > 0 ? ($now - $before) / $before * 100 : null;
@endphp

<main class="mx-auto max-w-7xl px-4 py-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $greeting }}, {{ explode(' ', auth()->user()->name)[0] }}</h1>
            <p class="mt-1 text-sm text-slate-500">Here is how {{ auth()->user()->branch?->name ?? 'the shop' }} is doing today.</p>
        </div>
        <div class="flex gap-2">
            @if (Route::has('reports.index'))
                <a href="{{ route('reports.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">
                    @include('partials.icon', ['name' => 'presentation-chart-line', 'class' => 'h-4 w-4'])
                    Reports
                </a>
            @endif
            <a href="{{ route('stock.receive') }}" class="inline-flex items-center gap-2 rounded-lg bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">
                @include('partials.icon', ['name' => 'truck', 'class' => 'h-4 w-4'])
                Receive delivery
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @include('partials.stat', [
            'label' => 'Sales today', 'icon' => 'banknotes',
            'value' => 'KSh ' . number_format($today['revenue']),
            'delta' => $change($today['revenue'], $yesterday['revenue']), 'deltaLabel' => 'vs this time yesterday',
        ])
        @include('partials.stat', [
            'label' => 'Customers served', 'icon' => 'shopping-cart',
            'value' => number_format($today['count']),
            'sub' => 'Avg basket KSh ' . number_format($today['avg_basket']),
        ])
        @include('partials.stat', [
            'label' => 'Gross profit today', 'icon' => 'arrow-trending-up',
            'value' => 'KSh ' . number_format($today['gross_profit']),
            'sub' => $today['margin'] . '% margin',
        ])
        @include('partials.stat', [
            'label' => 'Owed by customers', 'icon' => 'users',
            'value' => 'KSh ' . number_format($debt),
            'sub' => $debtors . ' ' . str('customer')->plural($debtors) . ' on credit',
        ])
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        @include('dashboard.partials.sales-chart')
        @include('dashboard.partials.payment-mix')
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        @include('dashboard.partials.top-products')
        @include('dashboard.partials.alerts')
        @include('dashboard.partials.recent-sales')
    </div>
</main>
@endsection
