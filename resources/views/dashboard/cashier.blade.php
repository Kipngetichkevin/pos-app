@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@include('partials.header')

<main class="mx-auto max-w-5xl px-4 py-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Habari, {{ explode(' ', auth()->user()->name)[0] }}</h1>
        <p class="mt-1 text-sm text-slate-500">
            @if ($shift)
                Your shift on till {{ $shift->till?->code }} started {{ $shift->opened_at->diffForHumans() }}.
            @else
                You have no open shift. Open one to start selling.
            @endif
        </p>
    </div>

    <a href="{{ route('pos') }}" class="mb-6 flex items-center justify-between rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-600 p-6 text-white shadow-lg shadow-indigo-600/20 hover:from-indigo-700 hover:to-violet-700">
        <span>
            <span class="block text-xl font-bold">{{ $shift ? 'Continue selling' : 'Open shift & start selling' }}</span>
            <span class="text-sm text-indigo-100">Scan, search or tap products</span>
        </span>
        @include('partials.icon', ['name' => 'shopping-cart', 'class' => 'h-10 w-10 opacity-80'])
    </a>

    @if ($summary)
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @include('partials.stat', ['label' => 'My sales this shift', 'icon' => 'banknotes', 'value' => 'KSh ' . number_format($summary['sales_total']), 'sub' => $summary['sales_count'] . ' sales'])
            @include('partials.stat', ['label' => 'M-Pesa received', 'icon' => 'device-phone-mobile', 'value' => 'KSh ' . number_format($summary['mpesa'])])
            @include('partials.stat', ['label' => 'Cash expected in drawer', 'icon' => 'banknotes', 'value' => 'KSh ' . number_format($summary['expected_cash'])])
        </div>

        <section class="mt-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-3 font-semibold text-slate-900">My latest sales</h2>
            <ul class="divide-y divide-slate-100">
                @forelse ($recent as $sale)
                    <li class="flex items-center justify-between py-2.5 text-sm">
                        <span><span class="font-mono text-xs font-semibold">{{ $sale->receipt_no }}</span> <span class="text-slate-400">&middot; {{ $sale->sold_at->format('H:i') }}</span></span>
                        <a href="{{ route('receipts.show', $sale) }}" target="_blank" class="tabular font-semibold text-slate-900 hover:text-indigo-600">{{ number_format($sale->grand_total, 2) }}</a>
                    </li>
                @empty
                    <li class="py-6 text-center text-slate-400">No sales on this shift yet.</li>
                @endforelse
            </ul>
        </section>
    @endif
</main>
@endsection
