<div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
    <button type="button" x-on:click="paying = false; focusScan()" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100" title="Back to the sale (Esc)">
        @include('partials.icon', ['name' => 'arrow-uturn-left', 'class' => 'h-5 w-5'])
    </button>
    <div class="flex-1">
        <h2 class="font-semibold text-slate-900">Payment</h2>
        <p class="text-xs text-slate-500" x-text="itemCount + ' item' + (itemCount === 1 ? '' : 's') + (customer ? ' · ' + customer.name : '')"></p>
    </div>
    <div class="text-right">
        <div class="text-xs text-slate-500">Total due</div>
        <div class="tabular text-xl font-bold text-slate-900" x-text="'KSh ' + money(total)"></div>
    </div>
</div>

<div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
    {{-- Cash --}}
    <div>
        <label class="mb-1.5 flex items-center gap-2 text-sm font-semibold text-slate-700">
            @include('partials.icon', ['name' => 'banknotes', 'class' => 'h-4 w-4 text-slate-400'])
            Cash received
        </label>
        <input x-ref="cash" x-model="cash" x-on:keydown.enter.prevent="checkout()" type="number" step="0.01" min="0" placeholder="0.00"
               class="tabular w-full rounded-xl border border-slate-200 px-4 py-3 text-2xl font-semibold">
        <div class="mt-2 grid grid-cols-3 gap-2">
            <button type="button" x-on:click="cashExact()" class="rounded-lg bg-indigo-50 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-100">Exact</button>
            @foreach ([100, 200, 500, 1000, 2000] as $note)
                <button type="button" x-on:click="cash = {{ $note }}" class="tabular rounded-lg bg-slate-100 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">{{ number_format($note) }}</button>
            @endforeach
        </div>
    </div>

    @include('pos.partials.mpesa')

    {{-- Credit (deni) --}}
    <template x-if="customer">
        <div class="rounded-xl border border-slate-200 p-3">
            <label class="mb-1.5 flex items-center gap-2 text-sm font-semibold text-slate-700">
                @include('partials.icon', ['name' => 'users', 'class' => 'h-4 w-4 text-slate-400'])
                On credit (deni) for <span x-text="customer.name"></span>
            </label>
            <div class="flex gap-2">
                <input x-model="credit" type="number" step="0.01" min="0" placeholder="0.00" class="tabular min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2">
                <button type="button" x-on:click="creditBalance()" class="whitespace-nowrap rounded-lg bg-amber-500 px-3 text-sm font-semibold text-white hover:bg-amber-600">Rest on credit</button>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                Owes <span x-text="money(customer.balance)"></span><span x-show="customer.credit_limit > 0" x-text="' · limit ' + money(customer.credit_limit)"></span>
            </p>
        </div>
    </template>
</div>

<div class="border-t border-slate-100 bg-slate-50/60 px-5 py-4">
    <div class="mb-3 flex items-center justify-between rounded-xl px-4 py-3"
         :class="balance > 0 ? 'bg-amber-50 text-amber-800 ring-1 ring-amber-200' : 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200'">
        <span class="font-semibold" x-text="balance > 0 ? 'Still to pay' : 'Change to give'"></span>
        <span class="tabular text-2xl font-bold" x-text="money(Math.abs(balance))"></span>
    </div>

    <p x-show="errors.checkout" x-text="errors.checkout" x-cloak class="mb-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700"></p>

    <button type="button" x-on:click="checkout()" :disabled="busy || balance > 0 || mpesa.status === 'pending'"
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 py-4 text-lg font-bold text-white shadow-lg shadow-emerald-600/25 hover:bg-emerald-700 disabled:bg-slate-200 disabled:text-slate-400 disabled:shadow-none">
        @include('partials.icon', ['name' => 'check-circle', 'class' => 'h-6 w-6'])
        <span x-text="busy ? 'Saving…' : 'Complete sale'">Complete sale</span>
    </button>
</div>
