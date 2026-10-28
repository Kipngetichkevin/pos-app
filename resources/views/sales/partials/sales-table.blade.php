<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Receipt</th>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Cashier</th>
                <th class="px-4 py-3 text-center">Items</th>
                <th class="px-4 py-3">Paid by</th>
                <th class="px-4 py-3 text-right">Total</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($sales as $sale)
                <tr class="{{ $sale->status === 'voided' ? 'opacity-50' : '' }}">
                    <td class="px-4 py-3 font-mono text-xs font-semibold">{{ $sale->receipt_no }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">{{ $sale->sold_at->format('d M Y, H:i') }}</td>
                    <td class="px-4 py-3">{{ $sale->cashier?->name }}</td>
                    <td class="px-4 py-3 text-center">{{ $sale->items_count }}</td>
                    <td class="px-4 py-3">
                        @foreach ($sale->payments as $payment)
                            <span class="mr-1 inline-block rounded px-2 py-0.5 text-xs {{ $payment->method === 'mpesa' ? ($payment->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800') : 'bg-slate-100 text-slate-700' }}">
                                {{ strtoupper($payment->method) }}{{ $payment->status === 'pending' ? ' (unverified)' : '' }}
                            </span>
                        @endforeach
                    </td>
                    <td class="px-4 py-3 text-right font-semibold">{{ number_format($sale->grand_total, 2) }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('sales.index', array_merge(request()->except('view'), ['view' => $sale->id])) }}" class="text-blue-600 hover:underline">View</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No sales found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $sales->links() }}</div>
