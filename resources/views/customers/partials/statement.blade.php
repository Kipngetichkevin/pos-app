<p class="-mt-3 mb-4 text-sm text-slate-500">Owes <b class="text-red-600">{{ number_format($statementCustomer->balance, 2) }}</b></p>
<div class="max-h-[60vh] overflow-y-auto">
    <table class="w-full text-left text-sm">
        <thead class="sticky top-0 bg-white text-xs uppercase text-slate-500">
            <tr>
                <th class="py-2">Date</th>
                <th class="py-2">Details</th>
                <th class="py-2 text-right">Took on credit</th>
                <th class="py-2 text-right">Paid / refunded</th>
                <th class="py-2 text-right">Balance</th>
                <th class="py-2 pl-3">By</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($statement as $t)
                <tr>
                    <td class="py-2 whitespace-nowrap">{{ $t->created_at->format('d M Y, H:i') }}</td>
                    <td class="py-2">
                        {{ $t->notes ?? ucfirst($t->type) }}
                        @if ($t->method) <span class="text-xs text-slate-500">{{ strtoupper($t->method) }} {{ $t->reference }}</span> @endif
                    </td>
                    <td class="py-2 text-right text-red-600">{{ (float) $t->amount > 0 ? number_format($t->amount, 2) : '' }}</td>
                    <td class="py-2 text-right text-green-700">{{ (float) $t->amount < 0 ? number_format(abs($t->amount), 2) : '' }}</td>
                    <td class="py-2 text-right font-semibold">{{ number_format($t->balance_after, 2) }}</td>
                    <td class="py-2 pl-3">{{ $t->user?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-slate-500">No transactions yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
