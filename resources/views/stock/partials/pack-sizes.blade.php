<div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <h3 class="mb-3 font-semibold">Pack sizes</h3>

    <table class="mb-4 w-full text-left text-sm">
        <thead class="text-xs uppercase text-slate-500">
            <tr>
                <th class="py-2">Pack</th>
                <th class="py-2 text-right">On shelf</th>
                <th class="py-2 text-right">Cost</th>
                <th class="py-2 text-right">Price</th>
                <th class="py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($packs as $pack)
                @php $onShelf = (float) ($pack->stockLevels->first()?->quantity ?? 0); @endphp
                <tr class="{{ $pack->is_active ? '' : 'opacity-50' }}">
                    <td class="py-2">
                        <div class="font-medium">{{ $pack->name }}</div>
                        <div class="text-xs text-slate-500">
                            {{ $fq($pack->pack_size) }} {{ $unit }}
                            @if ($pack->barcodes->isNotEmpty()) &middot; {{ $pack->barcodes->first()->barcode }} @endif
                        </div>
                    </td>
                    <td class="py-2 text-right font-semibold {{ $onShelf <= 0 ? 'text-red-600' : '' }}">{{ $fq($onShelf) }}</td>
                    <td class="py-2 text-right text-slate-600">{{ number_format($pack->cost_price, 2) }}</td>
                    <td class="py-2 text-right">
                        <form method="POST" action="{{ route('stock.packs.update', [$product, $pack]) }}">
                            @csrf @method('PATCH')
                            <input name="selling_price" type="number" step="0.01" min="0" value="{{ $pack->selling_price }}"
                                   onchange="this.form.submit()"
                                   class="w-24 rounded border border-slate-300 px-2 py-1 text-right">
                        </form>
                        @php $m = (float) $pack->selling_price > 0 ? (((float) $pack->selling_price - (float) $pack->cost_price) / (float) $pack->selling_price) * 100 : null; @endphp
                        @if ($m !== null)
                            <div class="text-xs {{ $m < 0 ? 'font-semibold text-red-600' : 'text-green-700' }}">{{ $m < 0 ? 'LOSS ' : '' }}{{ number_format($m, 1) }}% margin</div>
                        @endif
                    </td>
                    <td class="py-2 pl-2 text-right">
                        <form method="POST" action="{{ route('stock.packs.toggle', [$product, $pack]) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="text-xs text-slate-500 hover:underline">
                                {{ $pack->is_active ? 'Hide' : 'Show' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-6 text-center text-slate-500">No pack sizes yet. Add one below.</td></tr>
            @endforelse
        </tbody>
    </table>

    <form method="POST" action="{{ route('stock.packs.store', $product) }}" class="rounded-xl bg-slate-50 p-3">
        @csrf
        <div class="mb-2 text-sm font-semibold">Add a pack size</div>
        <div class="grid grid-cols-2 gap-2">
            <div>
                <input name="label" value="{{ old('label') }}" type="text" placeholder="Name, e.g. 1 kg" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('label') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <input name="size" value="{{ old('size') }}" type="number" step="0.001" min="0" placeholder="Size in {{ $unit }}, e.g. 1 or 0.5" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('size') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <input name="price" value="{{ old('price') }}" type="number" step="0.01" min="0" placeholder="Selling price (KSh)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('price') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <input name="barcode" value="{{ old('barcode') }}" type="text" placeholder="Barcode (optional)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('barcode') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <button type="submit" class="mt-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Add pack size</button>
    </form>
</div>
