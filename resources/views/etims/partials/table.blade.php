<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Sale</th>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Invoice no.</th>
                <th class="px-4 py-3">KRA</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($invoices as $inv)
                <tr>
                    <td class="px-4 py-3">
                        @if ($inv->sale)
                            <a href="{{ route('receipts.show', $inv->sale) }}" target="_blank" class="font-mono font-semibold text-blue-600 hover:underline">{{ $inv->sale->receipt_no }}</a>
                        @endif
                        <span class="block text-xs text-slate-500">{{ $inv->created_at->format('d M, H:i') }}</span>
                    </td>
                    <td class="px-4 py-3">
                        {{ $inv->type === 'credit_note' ? 'Credit note' : 'Invoice' }}
                        @if ($inv->saleReturn) <span class="block text-xs text-slate-500">{{ $inv->saleReturn->return_no }}</span> @endif
                    </td>
                    <td class="px-4 py-3 font-mono">{{ $inv->invoice_no ?? '-' }}</td>
                    <td class="px-4 py-3">
                        @if ($inv->status === 'sent')
                            <span class="rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">Accepted</span>
                            <span class="block font-mono text-xs text-slate-500">{{ $inv->sdc_id }} / {{ $inv->kra_receipt_no }}</span>
                        @elseif ($inv->status === 'not_required')
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">Not sent</span>
                            <span class="block text-xs text-slate-500">{{ $inv->last_error }}</span>
                        @else
                            <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $inv->status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800' }}">{{ $inv->status === 'failed' ? 'Failed' : 'Waiting' }}</span>
                            @if ($inv->last_error) <span class="block text-xs text-red-600">{{ $inv->last_error }}</span> @endif
                            <span class="block text-xs text-slate-500">
                                {{ $inv->attempts }} attempt(s){{ $inv->next_attempt_at ? ' · next ' . $inv->next_attempt_at->diffForHumans() : ' · needs attention' }}
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if (in_array($inv->status, ['queued', 'failed']))
                            <form method="POST" action="{{ route('etims.retry', $inv) }}">
                                @csrf
                                <button class="text-sm text-blue-600 hover:underline">Send now</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Nothing here.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $invoices->links() }}</div>
