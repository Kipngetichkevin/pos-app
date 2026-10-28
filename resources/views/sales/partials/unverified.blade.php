@if ($unverified->isNotEmpty())
    <div class="mb-4 rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-200">
        <div class="mb-2 font-semibold text-amber-900">Unverified M-Pesa codes (accepted by a manager)</div>
        @foreach ($unverified as $payment)
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-amber-200 py-2 text-sm">
                <span>
                    <span class="font-mono font-semibold">{{ $payment->reference }}</span>
                    &middot; KSh {{ number_format($payment->amount, 2) }}
                    &middot; Receipt {{ $payment->sale?->receipt_no }}
                    &middot; {{ $payment->created_at->format('d M, H:i') }}
                </span>
                <form method="POST" action="{{ route('sales.payments.verify', $payment) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-amber-600 px-3 py-1 text-xs font-semibold text-white hover:bg-amber-700">Check now</button>
                </form>
            </div>
        @endforeach
    </div>
@endif
