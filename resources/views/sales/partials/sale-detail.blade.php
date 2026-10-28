{{--
    Sale details popup, opened with ?view=ID.
    Uses its own modal markup (not partials.modal) to keep the mono receipt-number heading and subtitle line.
--}}
@php $closeUrl = route('sales.index', request()->except('view')); @endphp
<div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4">
    <div class="mt-10 w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-start justify-between">
            <div>
                <h3 class="font-mono text-lg font-bold">{{ $viewing->receipt_no }}</h3>
                <p class="text-sm text-slate-500">
                    {{ $viewing->sold_at->format('d M Y, H:i') }} &middot; {{ $viewing->cashier?->name }} &middot; Till {{ $viewing->till?->code }}
                </p>
            </div>
            <a href="{{ $closeUrl }}" class="text-2xl leading-none text-slate-400 hover:text-slate-600">&times;</a>
        </div>

        <table class="mb-4 w-full text-sm">
            <thead class="text-xs uppercase text-slate-500">
                <tr><th class="py-1 text-left">Item</th><th class="py-1 text-right">Qty</th><th class="py-1 text-right">Price</th><th class="py-1 text-right">Total</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($viewing->items as $item)
                    <tr>
                        <td class="py-1.5">{{ $item->product_name }}{{ $item->unit_name ? ' (' . $item->unit_name . ')' : '' }}</td>
                        <td class="py-1.5 text-right">{{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }}</td>
                        <td class="py-1.5 text-right">{{ number_format($item->unit_price, 2) }}</td>
                        <td class="py-1.5 text-right font-semibold">{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="space-y-1 border-t border-slate-200 pt-3 text-sm">
            <div class="flex justify-between text-base font-bold"><span>Total</span><span>{{ number_format($viewing->grand_total, 2) }}</span></div>
            @foreach ($viewing->payments as $payment)
                <div class="flex justify-between">
                    <span>
                        {{ strtoupper($payment->method) }}
                        @if ($payment->reference) <span class="font-mono">{{ $payment->reference }}</span> @endif
                        @if ($payment->method === 'mpesa')
                            @if ($payment->status === 'pending')
                                <span class="rounded bg-amber-100 px-1.5 text-xs text-amber-800">unverified</span>
                            @elseif ($payment->mpesa_transaction_id)
                                <span class="rounded bg-green-100 px-1.5 text-xs text-green-800">verified</span>
                            @else
                                <span class="rounded bg-slate-100 px-1.5 text-xs text-slate-600">not linked</span>
                            @endif
                        @endif
                    </span>
                    <span>{{ number_format($payment->amount, 2) }}</span>
                </div>
            @endforeach
            <div class="flex justify-between text-slate-500"><span>Change given</span><span>{{ number_format($viewing->change_given, 2) }}</span></div>
            <div class="flex justify-between text-slate-500"><span>VAT included</span><span>{{ number_format($viewing->tax_total, 2) }}</span></div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <a href="{{ route('receipts.show', $viewing) }}" target="_blank" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50">Reprint receipt</a>
            <a href="{{ $closeUrl }}" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Close</a>
        </div>
    </div>
</div>
