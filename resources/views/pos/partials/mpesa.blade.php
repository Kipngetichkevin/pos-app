<div class="rounded-xl border border-slate-200 p-3">
    <div class="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-700">
        <span class="flex h-5 w-5 items-center justify-center rounded bg-emerald-600 text-[10px] font-black text-white">M</span>
        M-Pesa
    </div>

    <div x-show="mpesa.status === 'success'" x-cloak class="flex items-center gap-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800 ring-1 ring-inset ring-emerald-200">
        @include('partials.icon', ['name' => 'check-circle', 'class' => 'h-6 w-6 shrink-0 text-emerald-600'])
        <div class="flex-1">
            <div class="font-semibold">KSh <span x-text="money(mpesa.amount)"></span> received</div>
            <div class="font-mono text-xs" x-text="mpesa.code"></div>
        </div>
        <button type="button" x-on:click="cancelMpesa()" class="text-xs font-medium underline">Remove</button>
    </div>

    <div x-show="mpesa.status === 'pending'" x-cloak class="flex items-center gap-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 ring-1 ring-inset ring-amber-200">
        <svg class="h-5 w-5 shrink-0 animate-spin text-amber-600" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
        <div class="flex-1">
            <div class="font-semibold">Waiting for the customer's PIN…</div>
            <div class="text-xs">KSh <span x-text="money(mpesa.amount)"></span> to <span x-text="mpesa.phone"></span></div>
        </div>
        <button type="button" x-on:click="cancelMpesa()" class="text-xs font-medium underline">Stop</button>
    </div>

    <div x-show="! mpesa.status" class="space-y-2">
        <div class="flex gap-2">
            <input x-model="mpesa.phone" type="tel" placeholder="07XX XXX XXX" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <input x-model="mpesa.amount" type="number" step="0.01" min="0" placeholder="Amount" class="tabular w-28 rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </div>
        <button type="button" x-on:click="sendStk()" :disabled="mpesa.sending"
                class="w-full rounded-lg bg-emerald-600 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
            <span x-text="mpesa.sending ? 'Sending…' : 'Send M-Pesa prompt to phone'">Send M-Pesa prompt to phone</span>
        </button>
        <p x-show="errors.mpesaPhone" x-text="errors.mpesaPhone" x-cloak class="text-xs text-rose-600"></p>
        <p x-show="mpesa.message" x-text="mpesa.message" x-cloak class="text-xs text-rose-600"></p>
        <input x-model="mpesa.code" type="text" placeholder="…or type the code from the SMS" class="w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm uppercase placeholder:font-sans placeholder:normal-case">
        <p x-show="errors.mpesaCode" x-text="errors.mpesaCode" x-cloak class="text-xs text-rose-600"></p>
        <template x-if="isManager">
            <label class="flex items-center gap-2 text-xs text-slate-500">
                <input x-model="mpesa.acceptUnverified" type="checkbox">
                Manager: accept the code without checking (internet down)
            </label>
        </template>

        <div x-show="recentPayments.length" class="pt-1">
            <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Paid to till in the last 30 min</div>
            <template x-for="p in recentPayments" :key="p.id">
                <button type="button" x-on:click="usePayment(p)"
                        class="mb-1 flex w-full items-center justify-between rounded-lg bg-emerald-50/60 px-3 py-2 text-left text-sm ring-1 ring-inset ring-emerald-100 hover:bg-emerald-50">
                    <span><span class="font-semibold text-slate-800" x-text="p.name"></span> <span class="font-mono text-xs text-slate-500" x-text="p.code"></span></span>
                    <span class="text-right"><span class="tabular font-bold text-slate-900" x-text="money(p.amount)"></span><span class="block text-[11px] text-slate-500" x-text="p.time"></span></span>
                </button>
            </template>
        </div>
    </div>
</div>
