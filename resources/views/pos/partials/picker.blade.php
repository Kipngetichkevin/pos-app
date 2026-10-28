{{-- Size / packaging picker --}}
<div x-show="picker" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" x-on:keydown.escape.window="picker = null">
    <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl" x-on:click.outside="picker = null">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-slate-400">Choose a size</div>
                <h3 class="text-lg font-bold text-slate-900" x-text="picker?.name"></h3>
            </div>
            <button type="button" x-on:click="picker = null" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                @include('partials.icon', ['name' => 'x-mark', 'class' => 'h-5 w-5'])
            </button>
        </div>
        <div class="max-h-[60vh] space-y-2 overflow-y-auto p-4">
            <template x-for="(c, i) in (picker?.choices || [])" :key="i">
                <button type="button" x-on:click="addItem(c.item); picker = null"
                        class="flex w-full items-center justify-between rounded-xl border border-slate-200 p-4 text-left hover:border-indigo-300 hover:bg-indigo-50/50">
                    <span>
                        <span class="block text-base font-semibold text-slate-800" x-text="c.label"></span>
                        <span class="text-xs" :class="c.warn ? 'font-semibold text-rose-600' : 'text-slate-500'" x-text="c.detail"></span>
                    </span>
                    <span class="tabular text-lg font-bold text-slate-900" x-text="money(c.item.price)"></span>
                </button>
            </template>
        </div>
    </div>
</div>
