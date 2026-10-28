@extends('layouts.app')

@section('title', 'Reports')

@section('content')
@include('partials.header')

@php
    $query = fn (array $extra = []) => array_filter(array_merge(
        $preset === 'custom' ? ['from' => $from->toDateString(), 'to' => $to->toDateString()] : ['range' => $preset],
        ['tab' => $tab],
        $extra,
    ));
@endphp

<main class="mx-auto max-w-7xl px-4 py-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Reports</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $from->format('j M Y') }}{{ $from->isSameDay($to) ? '' : ' – ' . $to->format('j M Y') }}
                &middot; {{ auth()->user()->branch?->name }}
            </p>
        </div>
        <div class="flex gap-2 print:hidden">
            <a href="{{ route('reports.export', $query()) }}" class="inline-flex items-center gap-2 rounded-lg bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">
                Download CSV
            </a>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-lg bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">
                @include('partials.icon', ['name' => 'printer', 'class' => 'h-4 w-4'])
                Print
            </button>
        </div>
    </div>

    @include('reports.partials.range')

    <nav class="mb-6 flex gap-1 overflow-x-auto border-b border-slate-200 print:hidden">
        @foreach (\App\Http\Controllers\ReportController::TABS as $key => $label)
            <a href="{{ route('reports.index', $query(['tab' => $key])) }}"
               class="-mb-px whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium {{ $tab === $key ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>

    @include('reports.partials.' . $tab)
</main>
@endsection
