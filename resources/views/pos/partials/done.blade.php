<div x-show="lastSale" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
    <div class="w-full max-w-sm rounded-3xl bg-white p-8 text-center shadow-2xl">
        <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            @include('partials.icon', ['name' => 'check-circle', 'class' => 'h-10 w-10'])
        </span>
        <div class="mt-4 text-lg font-bold text-slate-900">Sale complete</div>
        <div class="font-mono text-sm text-slate-500" x-text="lastSale?.receipt_no"></div>

        <div class="mt-6 rounded-2xl bg-slate-50 py-4">
            <div class="text-sm text-slate-500">Change to give</div>
            <div class="tabular text-5xl font-bold tracking-tight text-emerald-600" x-text="money(lastSale?.change)"></div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-2">
            <a :href="lastSale?.receipt_url" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 py-3 font-semibold text-slate-700 hover:bg-slate-50">
                @include('partials.icon', ['name' => 'printer', 'class' => 'h-5 w-5'])
                Receipt
            </a>
            <button type="button" x-ref="newSale" x-on:click="newSale()" class="rounded-xl bg-indigo-600 py-3 font-semibold text-white hover:bg-indigo-700">New sale</button>
        </div>
    </div>
</div>
