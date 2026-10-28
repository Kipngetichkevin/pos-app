<h3 class="mb-2 mt-8 text-lg font-bold">Recent deliveries</h3>
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">GRN</th>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Supplier</th>
                <th class="px-4 py-3">Invoice</th>
                <th class="px-4 py-3 text-center">Products</th>
                <th class="px-4 py-3 text-right">Total</th>
                <th class="px-4 py-3">Received by</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($recent as $grn)
                <tr>
                    <td class="px-4 py-3 font-mono text-xs font-semibold">{{ $grn->receipt_no }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">{{ $grn->received_at->format('d M Y, H:i') }}</td>
                    <td class="px-4 py-3">{{ $grn->supplier?->name ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $grn->invoice_no ?? '-' }}</td>
                    <td class="px-4 py-3 text-center">{{ $grn->items_count }}</td>
                    <td class="px-4 py-3 text-right font-semibold">{{ number_format($grn->total, 2) }}</td>
                    <td class="px-4 py-3">{{ $grn->user?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No deliveries yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
