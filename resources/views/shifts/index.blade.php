@extends('layouts.app')

@section('title', 'Shifts')

@section('content')
@include('partials.header')

<main class="mx-auto max-w-7xl px-4 py-6">
    <h2 class="mb-4 text-2xl font-bold text-slate-900">Shifts</h2>

    @include('partials.flash')

    @include('shifts.partials.table')

    <div class="mt-4">{{ $shifts->links() }}</div>
</main>
@endsection
