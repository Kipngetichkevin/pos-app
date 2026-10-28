@php $fq = fn ($q) => rtrim(rtrim(number_format((float) $q, 3), '0'), '.'); @endphp
<form method="POST" action="{{ route('transfers.receive', $transfer) }}" onsubmit="return confirm('Confirm these quantities arrived? They will be added to this branch.')">
    @csrf
    <div class="mb-3 rounded-lg bg-blue-50 p-3 text-sm text-blue-900 ring-1 ring-blue-200">
        Count what arrived. If something is missing or damaged, enter the smaller number: the difference is recorded as lost in transit.
    </div>
    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3 text-right">Sent</th>
                    <th class="w-36 px-4 py-3 text-center">Arrived</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($transfer->items as $item)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $item->product_name }}</td>
                        <td class="px-4 py-3 text-right">{{ $fq($item->quantity_sent) }} {{ $item->product?->unit?->short_name }}</td>
                        <td class="px-4 py-3 text-center">
                            <input name="received[{{ $item->id }}]" value="{{ old('received.' . $item->id, $fq($item->quantity_sent)) }}" type="number" min="0" step="any"
                                   class="w-24 rounded border border-slate-300 px-2 py-1 text-center">
                            @include('partials.error', ['field' => 'received.' . $item->id])
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-3">
        <input name="receive_notes" value="{{ old('receive_notes') }}" type="text" placeholder="Notes (e.g. 2 bottles broken)" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2">
        <button class="rounded-lg bg-green-600 px-6 py-2.5 font-semibold text-white hover:bg-green-700">Confirm arrival</button>
    </div>
</form>
