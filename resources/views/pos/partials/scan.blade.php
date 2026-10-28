<div class="relative">
    <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-400">
        @include('partials.icon', ['name' => 'magnifying-glass', 'class' => 'h-6 w-6'])
    </span>
    <input x-ref="scan" x-model="scan" x-on:keydown.enter.prevent="addScan()"
           type="text" placeholder="Scan a barcode or type a product name…" autocomplete="off"
           class="w-full rounded-2xl border border-slate-200 bg-white py-4 pl-14 pr-24 text-lg shadow-sm">
    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center">
        <kbd class="rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 font-sans text-xs text-slate-500">Enter</kbd>
    </span>

    <p x-show="errors.scan" x-text="errors.scan" x-cloak class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700"></p>

    <div x-show="results.length" x-cloak x-on:click.outside="results = []"
         class="absolute inset-x-0 top-full z-20 mt-2 overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200">
        <template x-for="r in results" :key="r.id">
            <button type="button" x-on:click="pick(r.id)" class="flex w-full items-center justify-between px-5 py-3 text-left hover:bg-indigo-50">
                <span class="font-medium text-slate-800" x-text="r.name"></span>
                <span class="tabular font-semibold text-slate-900" x-text="'KSh ' + money(r.price)"></span>
            </button>
        </template>
    </div>
</div>
