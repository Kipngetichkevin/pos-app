@extends('layouts.app')

@section('title', 'Packaging')

@section('content')
@include('partials.header')

<main class="mx-auto max-w-6xl px-4 py-6">
    <div class="mx-auto max-w-4xl">
        @php $fq = fn ($q) => rtrim(rtrim(number_format((float) $q, 3), '0'), '.'); @endphp
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">{{ $product->name }}: packaging</h2>
                <p class="text-sm text-slate-500">
                    Stock is counted in <b>{{ $base }}</b> &middot; {{ $fq($stock) }} {{ $base }} in stock
                    &middot; single price {{ number_format($product->selling_price, 2) }}
                    &middot; cost {{ number_format($avgCost, 2) }} per {{ $base }}
                </p>
            </div>
            <a href="{{ route('products.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Back to Products</a>
        </div>

        @include('partials.flash')

        @include('products.partials.packaging-table')

        @include('products.partials.packaging-form')
    </div>
</main>
@endsection
