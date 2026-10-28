<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Product</th>
                <th class="px-4 py-3">Category</th>
                <th class="px-4 py-3 text-right">In stock</th>
                <th class="px-4 py-3 text-right">Reorder at</th>
                <th class="px-4 py-3 text-right">Avg cost</th>
                <th class="px-4 py-3 text-right">Value</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($products as $product)
                @php
                    $level = $product->stockLevels->first();
                    $qty = (float) ($level?->quantity ?? 0);
                    $avg = (float) ($level?->average_cost ?? $product->cost_price);
                    $state = $qty <= 0 ? 'out' : ($qty <= (float) $product->reorder_level ? 'low' : 'ok');
                    $rowQuery = request()->only(['search', 'filter', 'page']);
                @endphp
                <tr>
                    <td class="px-4 py-3">
                        <div class="font-medium text-slate-900">{{ $product->name }}</div>
                        <div class="text-xs text-slate-500">{{ $product->parent ? 'Pack of ' . $product->parent->name : $product->sku }}</div>
                    </td>
                    <td class="px-4 py-3 text-slate-600">{{ $product->category?->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-right font-semibold {{ $state === 'out' ? 'text-red-600' : ($state === 'low' ? 'text-amber-600' : '') }}">
                        {{ $fmtQty($qty) }} {{ $product->unit?->short_name }}
                        @php $big = $product->packagings->first(); @endphp
                        @if ($big && $qty >= (float) $big->quantity)
                            @php $n = floor($qty / (float) $big->quantity); $rem = $qty - $n * (float) $big->quantity; @endphp
                            <div class="text-xs font-normal text-slate-500">= {{ $n }} {{ $big->name }}{{ $rem > 0 ? ' + ' . $fmtQty($rem) . ' ' . $product->unit?->short_name : '' }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right text-slate-500">{{ $fmtQty($product->reorder_level) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($avg, 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format(max($qty, 0) * $avg, 2) }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        @if (! $product->parent_id && ($product->packs_count > 0 || $product->unit?->allows_decimal))
                            <a href="{{ route('stock.packs', $product) }}" class="mr-3 text-green-700 hover:underline">Packs{{ $product->packs_count ? ' (' . $product->packs_count . ')' : '' }}</a>
                        @endif
                        <a href="{{ route('stock.index', $rowQuery + ['history' => $product->id]) }}" class="text-slate-600 hover:underline">History</a>
                        <a href="{{ route('stock.index', $rowQuery + ['adjust' => $product->id]) }}" class="ml-3 text-blue-600 hover:underline">Adjust</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No products found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $products->links() }}</div>
