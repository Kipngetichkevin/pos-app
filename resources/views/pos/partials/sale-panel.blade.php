<section class="flex max-h-[calc(100vh-7rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    {{-- Cart --}}
    <template x-if="! paying">
        <div class="flex min-h-0 flex-1 flex-col">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="font-semibold text-slate-900">Current sale</h2>
                    <p class="text-xs text-slate-500" x-text="itemCount ? itemCount + ' item' + (itemCount === 1 ? '' : 's') : 'Empty'"></p>
                </div>
                <button type="button" x-show="itemCount" x-on:click="confirm('Clear the whole sale?') && clearCart()"
                        class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50">
                    @include('partials.icon', ['name' => 'trash', 'class' => 'h-4 w-4'])
                    Clear
                </button>
            </div>

            <div class="border-b border-slate-100 px-5 py-3">
                @include('pos.partials.customer')
            </div>

            <div class="min-h-40 flex-1 overflow-y-auto">
                <template x-for="item in Object.values(cart)" :key="item.key">
                    <div class="flex gap-3 border-b border-slate-50 px-5 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-slate-800" x-text="item.name"></div>
                            <div class="text-xs text-slate-500">
                                <span x-text="money(priceOf(item))"></span>
                                <span x-text="'/ ' + (item.unit || 'pc')"></span>
                                <span x-show="isWholesale(item)" class="ml-1 rounded bg-indigo-50 px-1 font-semibold text-indigo-600">wholesale</span>
                            </div>
                            <div x-show="isWholesale(item) ? item.below_cost_wholesale : item.below_cost" class="mt-0.5 text-xs font-semibold text-rose-600"
                                 x-text="isManager ? 'Below cost (' + money(item.cost) + ')' : 'Below cost - call a manager'"></div>
                            <div class="mt-2 inline-flex items-center rounded-lg ring-1 ring-slate-200">
                                <button type="button" x-on:click="step(item.key, -1)" class="h-8 w-8 rounded-l-lg text-lg leading-none text-slate-500 hover:bg-slate-100">&minus;</button>
                                <input type="number" min="0" :step="item.decimal ? '0.001' : '1'" :value="item.quantity"
                                       x-on:change="updateQuantity(item.key, $event.target.value)"
                                       class="h-8 w-14 border-0 border-x border-slate-200 text-center text-sm font-semibold [appearance:textfield] focus:shadow-none [&::-webkit-inner-spin-button]:appearance-none">
                                <button type="button" x-on:click="step(item.key, 1)" class="h-8 w-8 rounded-r-lg text-lg leading-none text-slate-500 hover:bg-slate-100">+</button>
                            </div>
                        </div>
                        <div class="flex flex-col items-end justify-between">
                            <button type="button" x-on:click="removeItem(item.key)" class="rounded p-1 text-slate-300 hover:bg-rose-50 hover:text-rose-500" title="Remove">
                                @include('partials.icon', ['name' => 'x-mark', 'class' => 'h-4 w-4'])
                            </button>
                            <span class="tabular font-semibold text-slate-900" x-text="money(priceOf(item) * item.quantity)"></span>
                        </div>
                    </div>
                </template>

                <div x-show="! itemCount" class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                        @include('partials.icon', ['name' => 'shopping-cart', 'class' => 'h-7 w-7'])
                    </span>
                    <span class="text-sm text-slate-500">Scan, search or tap a product to start a sale.</span>
                </div>
            </div>

            <div class="border-t border-slate-100 bg-slate-50/60 px-5 py-4">
                <div class="mb-3 flex items-end justify-between">
                    <span class="text-sm text-slate-500">Total <span class="text-xs">(VAT inclusive)</span></span>
                    <span class="tabular text-3xl font-bold tracking-tight text-slate-900"><span class="mr-1 text-base font-semibold text-slate-400">KSh</span><span x-text="money(total)">0.00</span></span>
                </div>
                <button type="button" x-on:click="startPayment()" :disabled="! itemCount"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 py-4 text-lg font-bold text-white shadow-lg shadow-indigo-600/25 hover:bg-indigo-700 disabled:bg-slate-200 disabled:text-slate-400 disabled:shadow-none">
                    Charge <span x-show="itemCount" x-text="'KSh ' + money(total)"></span>
                    <kbd class="ml-1 rounded bg-white/20 px-1.5 font-sans text-xs" x-show="itemCount">F9</kbd>
                </button>
            </div>
        </div>
    </template>

    {{-- Payment --}}
    <template x-if="paying">
        <div class="flex min-h-0 flex-1 flex-col">
            @include('pos.partials.payment')
        </div>
    </template>
</section>
