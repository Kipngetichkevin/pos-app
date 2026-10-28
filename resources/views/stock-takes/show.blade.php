@extends('layouts.app')

@section('title', 'Stock take ' . $take->take_no)

@section('content')
@include('partials.header')

<main class="mx-auto max-w-7xl px-4 py-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Stock take <span class="font-mono">{{ $take->take_no }}</span></h2>
            <p class="text-sm text-slate-500">
                {{ $take->category?->name ?? 'Whole shop' }} &middot; started {{ $take->started_at->format('d M Y, H:i') }} by {{ $take->user?->name }}
                @if ($take->completed_at) &middot; completed {{ $take->completed_at->format('d M, H:i') }} by {{ $take->completedBy?->name }} @endif
                @if ($take->notes) &middot; {{ $take->notes }} @endif
            </p>
        </div>
        <a href="{{ route('stock-takes.index') }}" class="text-sm text-blue-600 hover:underline">&larr; All stock takes</a>
    </div>

    @include('partials.flash')
    @include('stock-takes.partials.progress')
    @include('stock-takes.partials.filters')

    @if ($take->isCounting())
        @include('stock-takes.partials.count-table')
    @else
        @include('stock-takes.partials.report-table')
    @endif
</main>
@endsection
