@extends('layouts.app')

@section('title', 'Audit log')

@section('content')
@include('partials.header')

<main class="mx-auto max-w-7xl px-4 py-6">
    <div class="mb-4">
        <h2 class="text-2xl font-bold text-slate-900">Audit log</h2>
        <p class="text-sm text-slate-500">Price changes, voids, refunds, stock adjustments and other sensitive actions. Entries cannot be edited or deleted.</p>
    </div>

    @include('audit.partials.filters')
    @include('audit.partials.counts')
    @include('audit.partials.table')
</main>
@endsection
