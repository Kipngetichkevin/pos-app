<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Product</th>
                <th class="px-4 py-3">Bought as</th>
                <th class="w-24 px-4 py-3">Qty</th>
                <th class="w-32 px-4 py-3">Total paid</th>
                <th class="px-4 py-3 text-right">Cost</th>
                <th class="px-4 py-3 text-right">Margin</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <template x-for="line in lines" :key="line.product_id">
                <tr>
                    <td class="px-4 py-3 font-medium" x-text="line.name"></td>
                    <td class="px-4 py-3">
                        <select x-on:change="setUnit(line, $event.target.value)" class="rounded border border-slate-300 px-2 py-1 text-sm">
                            <template x-for="u in line.units" :key="u.id">
                                <option :value="u.id" :selected="u.id === line.unit_id" x-text="u.name"></option>
                            </template>
                        </select>
                        <p x-show="lineError(line, 'unit_id')" x-text="lineError(line, 'unit_id')" class="text-xs text-red-600"></p>
                    </td>
                    <td class="px-4 py-3">
                        <input :value="line.quantity" x-on:input="setQuantity(line, $event.target.value)" type="number" step="0.001" min="0" class="w-20 rounded border border-slate-300 px-2 py-1 text-center">
                        <p x-show="lineError(line, 'quantity')" x-text="lineError(line, 'quantity')" class="text-xs text-red-600"></p>
                    </td>
                    <td class="px-4 py-3">
                        <input x-model="line.line_total" type="number" step="0.01" min="0" class="w-28 rounded border border-slate-300 px-2 py-1">
                        <p x-show="lineError(line, 'line_total')" x-text="lineError(line, 'line_total')" class="text-xs text-red-600"></p>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div x-show="Number(line.per) != 1" class="text-xs text-slate-500" x-text="money(calc(line).perUnit) + ' each'"></div>
                        <div class="font-semibold" x-text="money(calc(line).perBase) + ' / ' + line.base"></div>
                        <div class="text-xs text-slate-500" x-text="'= ' + fq(calc(line).baseQty) + ' ' + line.base + ' into stock'"></div>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <template x-if="calc(line).margin !== null">
                            <div>
                                <div x-text="money(calc(line).sell) + ' / ' + line.base"></div>
                                <div class="text-xs" :class="calc(line).margin < 0 ? 'font-semibold text-red-600' : 'text-green-700'"
                                     x-text="(calc(line).margin < 0 ? 'LOSS ' : '') + pct(calc(line).margin) + '%'"></div>
                            </div>
                        </template>
                        <template x-if="calc(line).margin === null">
                            <span class="text-xs text-slate-400">Sold in packs</span>
                        </template>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button type="button" x-on:click="removeLine(line)" class="text-red-500 hover:text-red-700">&times;</button>
                    </td>
                </tr>
            </template>
            <tr x-show="lines.length === 0">
                <td colspan="7" class="px-4 py-10 text-center text-slate-400">Scan or search for the products in this delivery.</td>
            </tr>
        </tbody>
    </table>
</div>
<p x-show="generalError" x-cloak x-text="generalError" class="text-sm text-red-600"></p>
