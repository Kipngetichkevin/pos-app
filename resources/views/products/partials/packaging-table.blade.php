<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Packaging</th>
                <th class="px-4 py-3 text-right">Contains</th>
                <th class="px-4 py-3 text-right">Cost</th>
                <th class="px-4 py-3">Selling price</th>
                <th class="px-4 py-3">Barcode</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($packagings as $pk)
                @php
                    $cost = $avgCost * (float) $pk->quantity;
                    $singles = (float) $product->selling_price * (float) $pk->quantity;
                    $m = $pk->selling_price !== null && (float) $pk->selling_price > 0 ? (((float) $pk->selling_price - $cost) / (float) $pk->selling_price) * 100 : null;
                @endphp
                <tr class="{{ $pk->is_active ? '' : 'opacity-50' }}">
                    <td class="px-4 py-3 font-medium">{{ $pk->name }}</td>
                    <td class="px-4 py-3 text-right">{{ $fq($pk->quantity) }} {{ $base }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($cost, 2) }}</td>
                    <td class="px-4 py-3">
                        <form method="POST" action="{{ route('products.packaging.update', [$product, $pk]) }}">
                            @csrf @method('PATCH')
                            <input name="price" type="number" step="0.01" min="0" value="{{ $pk->selling_price }}" placeholder="Not sold whole"
                                   onchange="this.form.submit()"
                                   class="w-32 rounded border border-slate-300 px-2 py-1">
                        </form>
                        @if ($m !== null)
                            <div class="text-xs {{ $m < 0 ? 'font-semibold text-red-600' : 'text-green-700' }}">{{ $m < 0 ? 'LOSS ' : '' }}{{ number_format($m, 1) }}% margin</div>
                            @if ($singles > (float) $pk->selling_price)
                                <div class="text-xs text-slate-500">Customer saves {{ number_format($singles - (float) $pk->selling_price, 2) }} vs singles</div>
                            @endif
                        @else
                            <div class="text-xs text-slate-400">Buying only</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs">{{ $pk->barcode ?? '-' }}</td>
                    <td class="px-4 py-3 text-right">
                        @if ($pk->is_default_purchase)
                            <span class="mr-2 rounded bg-blue-100 px-2 py-0.5 text-xs text-blue-800">Default for buying</span>
                        @else
                            <form method="POST" action="{{ route('products.packaging.default', [$product, $pk]) }}" class="inline">
                                @csrf @method('PATCH')
                                <button type="submit" class="mr-2 text-xs text-blue-600 hover:underline">Make default for buying</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('products.packaging.toggle', [$product, $pk]) }}" class="inline">
                            @csrf @method('PATCH')
                            <button type="submit" class="text-xs text-slate-500 hover:underline">{{ $pk->is_active ? 'Hide' : 'Show' }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No packaging yet. Add one below (e.g. Carton = 12).</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
