@php $fq = fn ($q) => $q === null ? '' : rtrim(rtrim(number_format((float) $q, 3), '0'), '.'); @endphp
<form method="POST" action="{{ route('stock-takes.count', $take) }}">
    @csrf
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3 text-right">System now</th>
                    <th class="w-40 px-4 py-3 text-center">Counted</th>
                    <th class="px-4 py-3 text-right">Difference</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                    @php $unit = $item->product?->unit?->short_name ?? 'pc'; @endphp
                    <tr>
                        <td class="px-4 py-2">
                            <span class="font-medium">{{ $item->product?->name }}</span>
                            <span class="block text-xs text-slate-500">{{ $item->product?->sku }}
                                @if ($item->counted_at) &middot; counted {{ $item->counted_at->format('H:i') }} by {{ $item->countedBy?->name }} @endif
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right text-slate-500">{{ $fq($levels[$item->product_id] ?? 0) }} {{ $unit }}</td>
                        <td class="px-4 py-2 text-center">
                            <input name="counts[{{ $item->id }}]" value="{{ old('counts.' . $item->id, $fq($item->counted_qty)) }}" type="number" min="0" step="any"
                                   class="w-24 rounded border border-slate-300 px-2 py-1 text-center">
                            @include('partials.error', ['field' => 'counts.' . $item->id])
                        </td>
                        <td class="px-4 py-2 text-right font-semibold {{ (float) $item->variance < 0 ? 'text-red-600' : ((float) $item->variance > 0 ? 'text-green-700' : 'text-slate-400') }}">
                            @if ($item->variance !== null)
                                {{ (float) $item->variance > 0 ? '+' : '' }}{{ $fq($item->variance) }}
                                <span class="block text-xs font-normal">KSh {{ number_format($item->varianceValue(), 2) }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400">Nothing here. Try another filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($items->count())
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <div>{{ $items->links() }}</div>
            <button class="rounded-lg bg-blue-600 px-6 py-2.5 font-semibold text-white hover:bg-blue-700">Save counts on this page</button>
        </div>
    @endif
</form>
