@extends('layouts.app')

@section('title', 'Close shift')

@section('content')
@include('partials.header')

<main class="mx-auto max-w-6xl px-4 py-6">
    <div class="mx-auto max-w-4xl">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-2xl font-bold text-slate-900">Close shift</h2>
            <a href="{{ route('pos') }}" class="text-sm text-blue-600 hover:underline">&larr; Back to POS</a>
        </div>

        @include('partials.flash')

        <div class="grid gap-4 md:grid-cols-2">
            @include('shifts.partials.summary')
            @include('shifts.partials.counter')
        </div>
    </div>
</main>
@endsection
