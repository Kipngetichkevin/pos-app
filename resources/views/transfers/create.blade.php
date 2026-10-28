@extends('layouts.app')

@section('title', 'Send stock')

@section('content')
@include('partials.header')

<main class="mx-auto max-w-7xl px-4 py-6" x-data="transferForm()">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-2xl font-bold text-slate-900">Send stock to another branch</h2>
        <a href="{{ route('transfers.index') }}" class="text-sm text-blue-600 hover:underline">&larr; All transfers</a>
    </div>

    @if ($branches->isEmpty())
        <div class="rounded-2xl bg-white p-10 text-center text-slate-500 shadow-sm ring-1 ring-slate-200">
            There is no other active branch to send stock to.
        </div>
    @else
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                @include('transfers.partials.scan')
                @include('transfers.partials.lines')
            </div>
            @include('transfers.partials.send')
        </div>
    @endif
</main>

@include('transfers.partials.script')
@endsection
