@extends('layouts.app')

@section('title', 'Stock take')

@section('content')
@include('partials.header')

<main class="mx-auto max-w-7xl px-4 py-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-2xl font-bold text-slate-900">Stock take</h2>
        <a href="{{ route('stock.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Back to stock</a>
    </div>

    @include('partials.flash')

    <div class="grid gap-4 lg:grid-cols-3">
        <div>
            @if ($open)
                <div class="rounded-2xl bg-amber-50 p-5 text-sm text-amber-900 ring-1 ring-amber-200">
                    <div class="font-semibold">{{ $open->take_no }} is in progress</div>
                    <p class="mt-1">Started {{ $open->started_at->diffForHumans() }}. Finish or cancel it before starting another.</p>
                    <a href="{{ route('stock-takes.show', $open) }}" class="mt-3 inline-block rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white hover:bg-amber-700">Continue counting</a>
                </div>
            @else
                @include('stock-takes.partials.start')
            @endif
        </div>

        <div class="lg:col-span-2">
            @include('stock-takes.partials.list')
        </div>
    </div>
</main>
@endsection
