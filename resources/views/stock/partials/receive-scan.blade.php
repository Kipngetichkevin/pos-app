<div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
    <input x-ref="scan" x-model="scan" x-on:keydown.enter.prevent="addScan()"
           type="text" placeholder="Scan barcode or type product name, then press Enter"
           class="w-full rounded-lg border border-slate-300 px-4 py-3 text-lg focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
    <p x-show="scanError" x-cloak x-text="scanError" class="mt-2 text-sm text-red-600"></p>
    <div x-show="results.length" x-cloak class="mt-2 divide-y divide-slate-100 rounded-lg ring-1 ring-slate-200">
        <template x-for="result in results" :key="result.id">
            <button type="button" x-on:click="pick(result.id)" x-text="result.name" class="block w-full px-4 py-2 text-left hover:bg-blue-50"></button>
        </template>
    </div>
</div>
