<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Product</th>
                <th class="px-4 py-3">Sent as</th>
                <th class="w-32 px-4 py-3 text-center">Quantity</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <template x-for="line in Object.values(lines)" :key="line.product_id">
                <tr>
                    <td class="px-4 py-3 font-medium">
                        <span x-text="line.name"></span>
                        <p x-show="errors['lines.' + line.product_id + '.quantity']" x-text="errors['lines.' + line.product_id + '.quantity']" class="text-xs text-red-600"></p>
                    </td>
                    <td class="px-4 py-3">
                        <select x-model="line.unit_id" class="rounded border border-slate-300 px-2 py-1">
                            <template x-for="u in line.units" :key="u.id">
                                <option :value="u.id" x-text="u.name" :selected="u.id === line.unit_id"></option>
                            </template>
                        </select>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <input x-model="line.quantity" type="number" min="0" step="any" class="w-24 rounded border border-slate-300 px-2 py-1 text-center">
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button type="button" x-on:click="delete lines[line.product_id]" class="text-red-500 hover:text-red-700">&times;</button>
                    </td>
                </tr>
            </template>
            <tr x-show="! Object.keys(lines).length">
                <td colspan="4" class="px-4 py-12 text-center text-slate-400">Scan or search the products you are sending.</td>
            </tr>
        </tbody>
    </table>
</div>
