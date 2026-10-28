@extends('layouts.app')

@section('title', 'Expenses')

@section('content')
@include('partials.header')

<main class="mx-auto max-w-7xl px-4 py-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-2xl font-bold text-slate-900">Expenses</h2>
        <a href="{{ route('expenses.index', ['create' => 1]) }}" class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">+ Record expense</a>
    </div>

    @include('partials.flash')
    @include('expenses.partials.filters')

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @include('expenses.partials.table')
        </div>
        @include('expenses.partials.summary')
    </div>

    @if ($showForm)
        @include('partials.modal', [
            'title' => 'Record expense',
            'close' => route('expenses.index'),
            'body' => 'expenses.partials.form',
        ])
    @endif
</main>
@endsection
