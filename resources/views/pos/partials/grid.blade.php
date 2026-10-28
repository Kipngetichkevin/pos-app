<div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
    <div class="-mx-1 mb-4 flex gap-2 overflow-x-auto px-1 pb-1">
        <button type="button" x-on:click="loadGrid(null)"
                :class="! category ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium">All</button>
        @foreach ($categories as $cat)
            <button type="button" x-on:click="loadGrid(@js($cat->id))"
                    :class="category === @js($cat->id) ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    class="shrink-0 rounded-full px-4 py-1.5 text-sm font-medium">{{ $cat->name }}</button>
        @endforeach
    </div>

    <div class="grid max-h-[calc(100vh-19rem)] min-h-64 grid-cols-2 gap-3 overflow-y-auto p-0.5 sm:grid-cols-3 2xl:grid-cols-4">
        <template x-for="p in grid" :key="p.id">
            <button type="button" x-on:click="pick(p.id)"
                    class="group flex flex-col rounded-xl border border-slate-200 bg-white p-3 text-left hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md hover:shadow-indigo-100 active:translate-y-0">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-bold" :class="tileColor(p.name)" x-text="initials(p.name)"></span>
                    <span x-show="p.options" class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-600" x-text="p.options + 1 + ' sizes'"></span>
                </div>
                <div class="line-clamp-2 min-h-[2.5rem] text-sm font-medium leading-5 text-slate-800" x-text="p.name"></div>
                <div class="mt-1 text-base font-bold text-slate-900" x-show="p.price > 0">
                    <span x-text="money(p.price)"></span>
                    <span class="text-xs font-normal text-slate-400" x-text="'/ ' + p.unit"></span>
                </div>
            </button>
        </template>
        <p x-show="! grid.length" class="col-span-full py-12 text-center text-sm text-slate-400">No products in this category.</p>
    </div>
</div>
