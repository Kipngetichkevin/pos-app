@if ($lossItems->isNotEmpty())
    <div class="mb-4 rounded-2xl bg-red-50 p-4 ring-1 ring-red-200">
        <div class="mb-2 font-semibold text-red-800">
            Sold below cost: KSh {{ number_format($lossItems->sum('loss'), 2) }} lost
        </div>
        <p class="mb-2 text-xs text-red-700">Usually a wrong buying price or a selling price set too low. Check these products' cost on the Stock screen.</p>
        <table class="w-full text-left text-sm">
            <thead class="text-xs uppercase text-red-700">
                <tr><th class="py-1">Product</th><th class="py-1 text-right">Times</th><th class="py-1 text-right">Qty</th><th class="py-1 text-right">Loss</th></tr>
            </thead>
            <tbody class="divide-y divide-red-100">
                @foreach ($lossItems as $li)
                    <tr>
                        <td class="py-1">{{ $li->product_name }}{{ $li->unit_name ? ' (' . $li->unit_name . ')' : '' }}</td>
                        <td class="py-1 text-right">{{ $li->times }}</td>
                        <td class="py-1 text-right">{{ rtrim(rtrim(number_format((float) $li->qty, 3), '0'), '.') }}</td>
                        <td class="py-1 text-right font-semibold text-red-700">{{ number_format((float) $li->loss, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
