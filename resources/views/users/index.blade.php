@extends('layouts.app')

@section('title', 'Users')

@section('content')
@include('partials.header')

<main class="mx-auto max-w-7xl px-4 py-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-2xl font-bold text-slate-900">Users</h2>
        <div class="flex gap-2">
            <form method="GET" action="{{ route('users.index') }}">
                <input name="search" value="{{ $search }}" type="text" placeholder="Search name or username..."
                       class="w-64 rounded-lg border border-slate-300 bg-white px-3 py-2">
            </form>
            <a href="{{ route('users.index', ['create' => 1]) }}" class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">+ Add user</a>
        </div>
    </div>

    @include('partials.flash')

    @include('users.partials.table')

    @if ($showForm)
        @include('partials.modal', [
            'title' => $editing ? 'Edit user' : 'Add user',
            'close' => route('users.index'),
            'body' => 'users.partials.form',
        ])
    @endif
</main>
@endsection
