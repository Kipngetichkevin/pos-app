@extends('layouts.app')

@section('title', 'Transfers')

@section('content')
@include('partials.header')

<main class="mx-auto max-w-7xl px-4 py-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-2xl font-bold text-slate-900">Transfers between branches</h2>
        <a href="{{ route('transfers.create') }}" class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">+ Send stock</a>
    </div>

    @include('partials.flash')

    @if ($incoming->isNotEmpty())
        @include('transfers.partials.incoming')
    @endif

    @include('transfers.partials.table')
</main>
@endsection
