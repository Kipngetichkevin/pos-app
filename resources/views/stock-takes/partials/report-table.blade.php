@php $fq = fn ($q) => $q === null ? '-' : rtrim(rtrim(number_format((float) $q, 3), '0'), '.'); @endphp
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Product</th>
                <th class="px-4 py-3 text-right">Expected</th>
                <th class="px-4 py-3 text-right">Counted</th>
                <th class="px-4 py-3 text-right">Difference</th>
                <th class="px-4 py-3 text-right">Value</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($items as $item)
                <tr>
                    <td class="px-4 py-2">
                        {{ $item->product?->name }}
                        @if ($item->countedBy) <span class="block text-xs text-slate-500">counted by {{ $item->countedBy->name }}</span> @endif
                    </td>
                    <td class="px-4 py-2 text-right">{{ $fq($item->expected_qty) }}</td>
                    <td class="px-4 py-2 text-right">{{ $fq($item->counted_qty) }}</td>
                    <td class="px-4 py-2 text-right font-semibold {{ (float) $item->variance < 0 ? 'text-red-600' : 'text-green-700' }}">
                        {{ $item->variance === null ? 'not counted' : ((float) $item->variance > 0 ? '+' : '') . $fq($item->variance) }}
                    </td>
                    <td class="px-4 py-2 text-right">{{ $item->variance === null ? '' : number_format($item->varianceValue(), 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No differences: the count matched the system.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $items->links() }}</div>
