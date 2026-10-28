@php $fq = fn ($q) => $q === null ? '-' : rtrim(rtrim(number_format((float) $q, 3), '0'), '.'); @endphp
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Product</th>
                <th class="px-4 py-3 text-right">Sent</th>
                <th class="px-4 py-3 text-right">Received</th>
                <th class="px-4 py-3 text-right">Lost on the way</th>
                <th class="px-4 py-3 text-right">Cost each</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($transfer->items as $item)
                <tr>
                    <td class="px-4 py-3 font-medium">{{ $item->product_name }}</td>
                    <td class="px-4 py-3 text-right">{{ $fq($item->quantity_sent) }} {{ $item->product?->unit?->short_name }}</td>
                    <td class="px-4 py-3 text-right">{{ $fq($item->quantity_received) }}</td>
                    <td class="px-4 py-3 text-right {{ $item->shortfall() > 0 ? 'font-semibold text-red-600' : 'text-slate-400' }}">
                        {{ $item->shortfall() > 0 ? $fq($item->shortfall()) . ' (KSh ' . number_format($item->shortfall() * (float) $item->unit_cost, 2) . ')' : '-' }}
                    </td>
                    <td class="px-4 py-3 text-right">{{ number_format($item->unit_cost, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="border-t border-slate-200 font-semibold">
            <tr><td class="px-4 py-3" colspan="4">Total value sent</td><td class="px-4 py-3 text-right">{{ number_format($transfer->total_cost, 2) }}</td></tr>
        </tfoot>
    </table>
</div>
