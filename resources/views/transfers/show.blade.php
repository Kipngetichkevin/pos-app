@extends('layouts.app')

@section('title', 'Transfer ' . $transfer->transfer_no)

@section('content')
@include('partials.header')

<main class="mx-auto max-w-5xl px-4 py-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Transfer <span class="font-mono">{{ $transfer->transfer_no }}</span></h2>
            <p class="text-sm text-slate-500">
                {{ $transfer->fromBranch?->name }} &rarr; {{ $transfer->toBranch?->name }}
                &middot; sent {{ $transfer->sent_at->format('d M Y, H:i') }} by {{ $transfer->sender?->name }}
                @if ($transfer->received_at) &middot; received {{ $transfer->received_at->format('d M, H:i') }} by {{ $transfer->receiver?->name }} @endif
            </p>
            @if ($transfer->notes) <p class="text-sm text-slate-500">Note: {{ $transfer->notes }}</p> @endif
            @if ($transfer->receive_notes) <p class="text-sm text-slate-500">On arrival: {{ $transfer->receive_notes }}</p> @endif
        </div>
        <a href="{{ route('transfers.index') }}" class="text-sm text-blue-600 hover:underline">&larr; All transfers</a>
    </div>

    @include('partials.flash')

    @if ($canReceive)
        @include('transfers.partials.receive')
    @else
        @include('transfers.partials.items')
    @endif

    @if ($canCancel)
        <form method="POST" action="{{ route('transfers.cancel', $transfer) }}" class="mt-4"
              onsubmit="return confirm('Cancel this transfer? All the stock comes back to this branch.')">
            @csrf
            <button class="rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Cancel transfer (not sent)</button>
        </form>
    @endif
</main>
@endsection
