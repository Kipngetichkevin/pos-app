@extends('layouts.app')

@section('title', 'KRA eTIMS')

@section('content')
@include('partials.header')

<main class="mx-auto max-w-7xl px-4 py-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">KRA eTIMS</h2>
            <p class="text-sm text-slate-500">Every sale and refund is sent to KRA in the background. Checkout never waits for it.</p>
        </div>
        @if ($enabled && (($counts['failed'] ?? 0) + ($counts['queued'] ?? 0)) > 0)
            <form method="POST" action="{{ route('etims.retry-all') }}">
                @csrf
                <button class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">Send all waiting now</button>
            </form>
        @endif
    </div>

    @include('partials.flash')
    @include('etims.partials.setup')
    @include('etims.partials.counts')
    @include('etims.partials.table')
</main>
@endsection
