<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Product</th>
                <th class="px-4 py-3">Category</th>
                <th class="px-4 py-3 text-right">Cost</th>
                <th class="px-4 py-3 text-right">Price</th>
                <th class="px-4 py-3 text-right">Stock</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($products as $product)
                @php
                    $qty = (float) ($product->stockLevels->first()?->quantity ?? 0);
                    $low = $product->track_stock && $qty <= (float) $product->reorder_level;
                @endphp
                <tr class="{{ $product->is_active ? '' : 'opacity-50' }}">
                    <td class="px-4 py-3">
                        <div class="font-medium text-slate-900">{{ $product->name }}</div>
                        <div class="text-xs text-slate-500">
                            {{ $product->sku }}
                            @if ($product->barcodes->isNotEmpty())
                                &middot; {{ $product->barcodes->first()->barcode }}
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3 text-slate-600">{{ $product->category?->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($product->cost_price, 2) }}<span class="text-xs text-slate-400"> /{{ $product->unit?->short_name }}</span></td>
                    <td class="px-4 py-3 text-right font-semibold">{{ number_format($product->selling_price, 2) }}<span class="text-xs font-normal text-slate-400"> /{{ $product->unit?->short_name }}</span>@if ((float) $product->selling_price > 0 && (float) $product->selling_price < (float) $product->cost_price)<div class="text-xs font-semibold text-red-600">Below cost</div>@endif</td>
                    <td class="px-4 py-3 text-right {{ $low ? 'font-semibold text-red-600' : '' }}">
                        @if ($product->track_stock)
                            {{ rtrim(rtrim(number_format($qty, 3), '0'), '.') }} {{ $product->unit?->short_name }}
                        @else
                            <span class="text-slate-400">n/a</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($product->is_active)
                            <span class="rounded bg-green-100 px-2 py-0.5 text-xs text-green-800">Active</span>
                        @else
                            <span class="rounded bg-slate-200 px-2 py-0.5 text-xs text-slate-700">Inactive</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        <a href="{{ route('products.packaging', $product) }}" class="mr-3 text-green-700 hover:underline">Packaging</a><a href="{{ route('products.index', array_merge($listQuery, ['edit' => $product->id])) }}" class="text-blue-600 hover:underline">Edit</a>
                        <form method="POST" action="{{ route('products.toggle', $product) }}" class="inline">
                            @csrf @method('PATCH')
                            <button class="ml-3 text-slate-500 hover:underline">
                                {{ $product->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                        No products found. Click "+ Add product" to create one.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
