@php
    $fq = fn ($q) => rtrim(rtrim(number_format((float) $q, 3), '0'), '.');
    $prices = $sale->items->mapWithKeys(fn ($i) => [$i->id => (float) $i->quantity > 0 ? (float) $i->line_total / (float) $i->quantity : 0]);
    $remainingAll = $sale->items->mapWithKeys(fn ($i) => [$i->id => round((float) $i->quantity - (float) $i->quantity_returned, 3)]);
@endphp
<div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"
     x-data="{
        qty: @js(collect(old('qty', []))->map(fn ($v) => (string) $v)),
        prices: @js($prices),
        remaining: @js($remainingAll),
        method: @js(old('refundMethod', 'cash')),
        get total() { return Object.entries(this.qty).reduce((s, [id, q]) => s + (Number(q) || 0) * (this.prices[id] || 0), 0) },
        returnAll() { for (const [id, r] of Object.entries(this.remaining)) { if (r > 0) this.qty[id] = String(r) } },
     }">
    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
        <div>
            <div class="font-mono text-lg font-bold">{{ $sale->receipt_no }}</div>
            <div class="text-sm text-slate-500">
                {{ $sale->sold_at->format('d M Y, H:i') }} &middot; {{ $sale->cashier?->name }}
                &middot; paid by {{ $sale->payments->pluck('method')->map(fn ($m) => strtoupper($m))->join(' + ') }}
            </div>
        </div>
        <span class="rounded px-2 py-1 text-xs font-semibold uppercase
            {{ $sale->status === 'completed' ? 'bg-green-100 text-green-800' : ($sale->status === 'voided' ? 'bg-slate-200 text-slate-700' : 'bg-amber-100 text-amber-800') }}">
            {{ str_replace('_', ' ', $sale->status) }}
        </span>
    </div>

    @if ($sale->status === 'voided')
        <p class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">This sale was voided{{ $sale->void_reason ? ': ' . $sale->void_reason : '' }}.</p>
    @else
        <form method="POST" action="{{ route('returns.store', $sale) }}" onsubmit="return confirm('Process this return and refund the customer?')">
            @csrf
            <table class="mb-3 w-full text-left text-sm">
                <thead class="text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2">Item</th>
                        <th class="py-2 text-right">Sold</th>
                        <th class="py-2 text-right">Returned</th>
                        <th class="py-2 text-center">Return now</th>
                        <th class="py-2 text-center">Back on shelf</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($sale->items as $item)
                        @php $remaining = $remainingAll[$item->id]; @endphp
                        <tr>
                            <td class="py-2">
                                {{ $item->product_name }}{{ $item->unit_name ? ' (' . $item->unit_name . ')' : '' }}
                                <span class="block text-xs text-slate-500">{{ number_format($item->unit_price, 2) }} each</span>
                            </td>
                            <td class="py-2 text-right">{{ $fq($item->quantity) }}</td>
                            <td class="py-2 text-right">{{ $fq($item->quantity_returned) }}</td>
                            <td class="py-2 text-center">
                                @if ($remaining > 0)
                                    <input name="qty[{{ $item->id }}]" x-model="qty['{{ $item->id }}']" type="number" min="0" max="{{ $remaining }}" step="any" placeholder="0"
                                           class="w-20 rounded border border-slate-300 px-2 py-1 text-center">
                                    @include('partials.error', ['field' => 'qty.' . $item->id])
                                @else
                                    <span class="text-xs text-slate-400">All returned</span>
                                @endif
                            </td>
                            <td class="py-2 text-center">
                                @if ($remaining > 0)
                                    <input name="restock[{{ $item->id }}]" value="1" type="checkbox" @checked(old('restock.' . $item->id, ! old('_token'))) class="rounded border-slate-300">
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <button type="button" x-on:click="returnAll()" class="mb-4 text-sm text-blue-600 hover:underline">Return all remaining items</button>

            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium">Reason *</label>
                    <select name="reason" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">-- Choose --</option>
                        @foreach ($reasons as $r)
                            <option value="{{ $r }}" @selected(old('reason') === $r)>{{ $r }}</option>
                        @endforeach
                    </select>
                    @include('partials.error', ['field' => 'reason'])
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Refund by</label>
                    <div class="flex gap-4 py-2 text-sm">
                        <label class="flex items-center gap-1"><input name="refundMethod" x-model="method" type="radio" value="cash"> Cash</label>
                        <label class="flex items-center gap-1"><input name="refundMethod" x-model="method" type="radio" value="mpesa"> M-Pesa</label>
                        @if ($sale->customer_id)
                            <label class="flex items-center gap-1"><input name="refundMethod" x-model="method" type="radio" value="credit"> Reduce debt</label>
                        @endif
                    </div>
                    @include('partials.error', ['field' => 'refundMethod'])
                </div>
                <div class="md:col-span-2" x-show="method === 'mpesa'" x-cloak>
                    <input name="refundReference" value="{{ old('refundReference') }}" type="text" placeholder="M-Pesa code of the money sent back" class="w-full rounded-lg border border-slate-300 px-3 py-2 uppercase">
                    @include('partials.error', ['field' => 'refundReference'])
                </div>
                @if ($needsPin)
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium">Manager PIN (approval)</label>
                        <input name="pin" type="password" inputmode="numeric" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        @include('partials.error', ['field' => 'pin'])
                    </div>
                @endif
            </div>

            @include('partials.error', ['field' => 'sale'])

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
                <div class="text-lg">Refund: <b>KSh <span x-text="money(total)">0.00</span></b></div>
                <button class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">Process return</button>
            </div>
        </form>

        @if ($canVoid)
            @include('returns.partials.void')
        @endif
    @endif

    @if ($sale->returns->isNotEmpty())
        <div class="mt-4 border-t border-slate-200 pt-3 text-sm">
            <div class="mb-1 font-semibold">Earlier returns on this sale</div>
            @foreach ($sale->returns as $ret)
                <div class="flex justify-between text-slate-600">
                    <span>{{ $ret->return_no }} &middot; {{ $ret->returned_at->format('d M, H:i') }} &middot; {{ $ret->reason }}</span>
                    <a href="{{ route('returns.receipt', $ret) }}" target="_blank" class="text-blue-600 hover:underline">KSh {{ number_format($ret->total_refunded, 2) }}</a>
                </div>
            @endforeach
        </div>
    @endif
</div>
