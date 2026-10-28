<div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
    <input x-ref="scan" x-model="scan" x-on:keydown.enter.prevent="lookup()" autofocus autocomplete="off"
           type="text" placeholder="Scan barcode or type product name, then press Enter"
           class="w-full rounded-lg border border-slate-300 px-4 py-3 text-lg focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
    <p x-show="errors.scan" x-text="errors.scan" x-cloak class="mt-2 text-sm text-red-600"></p>
    <div x-show="results.length" x-cloak class="mt-2 divide-y divide-slate-100 rounded-lg ring-1 ring-slate-200">
        <template x-for="r in results" :key="r.id">
            <button type="button" x-on:click="lookup(r.id)" class="block w-full px-4 py-2 text-left hover:bg-blue-50" x-text="r.name"></button>
        </template>
    </div>
</div>
