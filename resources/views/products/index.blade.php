@extends('layouts.app')

@section('title', 'Products')

@section('content')
@include('partials.header')

<main class="mx-auto max-w-6xl px-4 py-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-2xl font-bold text-slate-900">Products</h2>
        <div class="flex gap-2">
            <form method="GET" action="{{ route('products.index') }}">
                <input name="search" value="{{ $search }}" type="text" placeholder="Search name, SKU or barcode..."
                       class="w-72 rounded-lg border border-slate-300 bg-white px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </form>
            <a href="{{ route('products.index', array_merge($listQuery, ['create' => 1])) }}" class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">
                + Add product
            </a>
        </div>
    </div>

    @include('partials.flash')

    @include('products.partials.table')

    <div class="mt-4">{{ $products->links() }}</div>

    @if ($showForm)
        @include('partials.modal', [
            'title' => $editing ? 'Edit product' : 'Add product',
            'close' => route('products.index', $listQuery),
            'body' => 'products.partials.form',
            'width' => 'max-w-2xl',
        ])
    @endif
</main>
@endsection
