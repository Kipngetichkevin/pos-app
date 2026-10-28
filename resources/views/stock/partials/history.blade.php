@php
    $typeLabels = ['opening' => 'Opening stock', 'sale' => 'Sale', 'return' => 'Customer return', 'purchase' => 'Delivery', 'adjustment' => 'Adjustment', 'damage' => 'Damaged / expired', 'transfer_in' => 'Transfer in', 'transfer_out' => 'Transfer out'];
@endphp
<div class="max-h-[60vh] overflow-y-auto">
    <table class="w-full text-left text-sm">
        <thead class="sticky top-0 bg-white text-xs uppercase text-slate-500">
            <tr>
                <th class="py-2">Date</th>
                <th class="py-2">Type</th>
                <th class="py-2 text-right">Change</th>
                <th class="py-2 text-right">Balance</th>
                <th class="py-2 pl-4">By</th>
                <th class="py-2">Reason</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($movements as $move)
                <tr>
                    <td class="py-2 whitespace-nowrap">{{ $move->created_at->format('d M Y, H:i') }}</td>
                    <td class="py-2">{{ $typeLabels[$move->type] ?? $move->type }}</td>
                    <td class="py-2 text-right font-semibold {{ $move->quantity < 0 ? 'text-red-600' : 'text-green-700' }}">
                        {{ $move->quantity > 0 ? '+' : '' }}{{ $fmtQty($move->quantity) }}
                    </td>
                    <td class="py-2 text-right">{{ $fmtQty($move->balance_after) }}</td>
                    <td class="py-2 pl-4">{{ $move->user?->name }}</td>
                    <td class="py-2 text-slate-500">{{ $move->reason }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-slate-500">No stock movements yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
