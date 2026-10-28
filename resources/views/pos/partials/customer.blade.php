{{-- Customer (optional): needed for credit (deni) and wholesale prices --}}
<template x-if="customer">
    <div class="flex items-center gap-3 rounded-xl bg-indigo-50/70 px-3 py-2 ring-1 ring-inset ring-indigo-100">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white" x-text="initials(customer.name)"></span>
        <div class="min-w-0 flex-1 text-sm">
            <div class="truncate font-semibold text-slate-800">
                <span x-text="customer.name"></span>
                <span x-show="customer.price_level === 'wholesale'" class="ml-1 rounded bg-indigo-600 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white">wholesale</span>
            </div>
            <div class="text-xs" :class="customer.balance > 0 ? 'font-semibold text-rose-600' : 'text-slate-500'">
                <span x-text="customer.phone || 'No phone'"></span> &middot; owes <span x-text="money(customer.balance)"></span>
            </div>
        </div>
        <button type="button" x-on:click="clearCustomer()" class="rounded p-1 text-slate-400 hover:bg-white hover:text-slate-600" title="Remove customer">
            @include('partials.icon', ['name' => 'x-mark', 'class' => 'h-4 w-4'])
        </button>
    </div>
</template>
<template x-if="! customer">
    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
            @include('partials.icon', ['name' => 'users', 'class' => 'h-4 w-4'])
        </span>
        <input x-model="customerSearch" x-on:input.debounce.300ms="searchCustomers()" type="text" placeholder="Add customer (name or phone)"
               class="w-full rounded-lg border border-slate-200 py-2 pl-9 pr-3 text-sm">
        <div x-show="customerSearch.trim().length >= 2" x-cloak class="absolute inset-x-0 top-full z-20 mt-1 overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-slate-200">
            <template x-for="c in customerResults" :key="c.id">
                <button type="button" x-on:click="selectCustomer(c)" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-indigo-50">
                    <span><span class="font-medium" x-text="c.name"></span> <span class="text-xs text-slate-500" x-text="c.phone"></span></span>
                    <span class="tabular text-xs" :class="c.balance > 0 ? 'text-rose-600' : 'text-slate-400'" x-text="money(c.balance)"></span>
                </button>
            </template>
            <div class="flex gap-2 border-t border-slate-100 bg-slate-50 p-2">
                <input x-model="newCustomerPhone" type="tel" placeholder="Phone for new customer" class="min-w-0 flex-1 rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                <button type="button" x-on:click="quickAddCustomer()" class="whitespace-nowrap rounded-lg bg-indigo-600 px-3 text-xs font-semibold text-white hover:bg-indigo-700">+ Add new</button>
            </div>
        </div>
    </div>
</template>
<p x-show="errors.customer" x-text="errors.customer" x-cloak class="mt-1 text-xs text-rose-600"></p>
