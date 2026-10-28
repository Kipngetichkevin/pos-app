<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Time</th>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Customer</th>
                <th class="px-4 py-3">M-Pesa code</th>
                <th class="px-4 py-3 text-right">Amount</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Sale</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($mpesa as $tx)
                @php
                    $colors = ['success' => 'bg-green-100 text-green-800', 'pending' => 'bg-amber-100 text-amber-800', 'cancelled' => 'bg-slate-200 text-slate-700', 'failed' => 'bg-red-100 text-red-800'];
                    $linked = $tx->payments->first()?->sale;
                @endphp
                <tr>
                    <td class="px-4 py-3 whitespace-nowrap">{{ $tx->created_at->format('d M, H:i') }}</td>
                    <td class="px-4 py-3 text-xs">{{ $tx->type === 'stk_push' ? 'STK prompt' : 'Paid from phone' }}</td>
                    <td class="px-4 py-3">
                        {{ $tx->first_name ?: '' }}
                        <span class="text-xs text-slate-500">{{ $tx->phone }}</span>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs font-semibold">{{ $tx->mpesa_receipt ?: '-' }}</td>
                    <td class="px-4 py-3 text-right font-semibold">{{ number_format((float) $tx->amount, 2) }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded px-2 py-0.5 text-xs {{ $colors[$tx->status] ?? 'bg-slate-100' }}">{{ ucfirst($tx->status) }}</span>
                        @if (in_array($tx->status, ['failed', 'cancelled']) && $tx->result_desc)
                            <div class="mt-1 max-w-xs text-xs text-slate-500">{{ $tx->result_desc }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($linked)
                            <a href="{{ route('sales.index', array_merge(request()->except('view'), ['view' => $linked->id])) }}" class="font-mono text-xs text-blue-600 hover:underline">{{ $linked->receipt_no }}</a>
                        @elseif ($tx->status === 'success')
                            <span class="rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-800">Not attached</span>
                        @else
                            <span class="text-xs text-slate-400">-</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No M-Pesa transactions found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $mpesa->links() }}</div>
