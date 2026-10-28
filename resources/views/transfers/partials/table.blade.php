<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Transfer</th>
                <th class="px-4 py-3">Direction</th>
                <th class="px-4 py-3">Items</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3 text-right">Value (cost)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($transfers as $t)
                @php $out = $t->from_branch_id === $branchId; @endphp
                <tr>
                    <td class="px-4 py-3">
                        <a href="{{ route('transfers.show', $t) }}" class="font-mono font-semibold text-blue-600 hover:underline">{{ $t->transfer_no }}</a>
                        <span class="block text-xs text-slate-500">{{ $t->sent_at->format('d M Y, H:i') }} &middot; {{ $t->sender?->name }}</span>
                    </td>
                    <td class="px-4 py-3">
                        @if ($out)
                            <span class="text-red-600">Out &rarr;</span> {{ $t->toBranch?->name }}
                        @else
                            <span class="text-green-700">In &larr;</span> {{ $t->fromBranch?->name }}
                        @endif
                    </td>
                    <td class="px-4 py-3">{{ $t->items_count }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded px-2 py-0.5 text-xs font-semibold uppercase
                            {{ $t->status === 'received' ? 'bg-green-100 text-green-800' : ($t->status === 'sent' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-600') }}">
                            {{ $t->status === 'sent' ? 'in transit' : $t->status }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">{{ number_format($t->total_cost, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No transfers yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $transfers->links() }}</div>
