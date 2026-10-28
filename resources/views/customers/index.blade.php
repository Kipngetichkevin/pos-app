@extends('layouts.app')

@section('title', 'Customers')

@section('content')
@include('partials.header')

<main class="mx-auto max-w-6xl px-4 py-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-2xl font-bold text-slate-900">Customers &amp; credit</h2>
        <div class="flex gap-2">
            <form method="GET" action="{{ route('customers.index') }}" class="flex gap-2">
                <input name="search" value="{{ $search }}" type="text" placeholder="Search name or phone..." class="w-56 rounded-lg border border-slate-300 bg-white px-3 py-2">
                <select name="filter" onchange="this.form.submit()" class="rounded-lg border border-slate-300 bg-white px-3 py-2">
                    <option value="owing" @selected($filter === 'owing')>Owing money</option>
                    <option value="all" @selected($filter === 'all')>All customers</option>
                </select>
            </form>
            <a href="{{ route('customers.index', array_merge(request()->only(['search', 'filter', 'page']), ['create' => 1])) }}" class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">+ Add customer</a>
        </div>
    </div>

    @include('partials.flash')

    <div class="mb-4 grid grid-cols-2 gap-3">
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="text-xs uppercase text-slate-500">Total owed to the shop</div>
            <div class="mt-1 text-xl font-bold text-red-600">KSh {{ number_format($totalOwed, 2) }}</div>
        </div>
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="text-xs uppercase text-slate-500">Customers owing</div>
            <div class="mt-1 text-xl font-bold">{{ $debtors }}</div>
        </div>
    </div>

    @include('customers.partials.table')
    <div class="mt-4">{{ $customers->links() }}</div>

    @if ($showForm)
        @include('partials.modal', [
            'title' => $editing ? 'Edit customer' : 'Add customer',
            'close' => $listUrl,
            'body' => 'customers.partials.form',
        ])
    @endif

    @if ($paying)
        @include('partials.modal', [
            'title' => 'Receive payment',
            'close' => $listUrl,
            'body' => 'customers.partials.payment',
            'width' => 'max-w-md',
        ])
    @endif

    @if ($statementCustomer)
        @include('partials.modal', [
            'title' => 'Statement: ' . $statementCustomer->name,
            'close' => $listUrl,
            'body' => 'customers.partials.statement',
            'width' => 'max-w-3xl',
        ])
    @endif
</main>
@endsection
