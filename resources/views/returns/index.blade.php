@extends('layouts.app')

@section('title', 'Returns')

@section('content')
@include('partials.header')

<main class="mx-auto max-w-7xl px-4 py-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-2xl font-bold text-slate-900">Returns &amp; voids</h2>
        <a href="{{ route('pos') }}" class="text-sm text-blue-600 hover:underline">&larr; Back to POS</a>
    </div>

    @include('partials.flash')

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4">
            @include('returns.partials.find')
            @include('returns.partials.recent')
        </div>

        <div class="lg:col-span-2">
            @if (! $sale)
                <div class="rounded-2xl bg-white p-10 text-center text-slate-500 shadow-sm ring-1 ring-slate-200">
                    Find a sale by receipt number, or pick one from this shift's sales.
                </div>
            @else
                @include('returns.partials.sale')
            @endif
        </div>
    </div>
</main>
@endsection
