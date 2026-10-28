<div class="space-y-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
    <div>
        <label class="mb-1 block text-sm font-medium">Supplier</label>
        <select x-model="supplier_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            <option value="">-- No supplier --</option>
            @foreach ($suppliers as $supplier)
                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
            @endforeach
        </select>
        <input x-model="newSupplier" type="text" placeholder="...or type a new supplier name" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium">Supplier invoice / delivery note no.</label>
        <input x-model="invoice_no" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2">
    </div>
    <label class="flex items-center gap-2 text-sm">
        <input x-model="updateCostPrice" type="checkbox" class="rounded border-slate-300">
        Update products' buying price from this delivery
    </label>
    <div class="flex items-baseline justify-between border-t border-slate-200 pt-3">
        <span class="text-slate-500">Delivery total</span>
        <span class="text-2xl font-bold" x-text="money(total)">0.00</span>
    </div>
    <button type="button" x-on:click="save()" x-bind:disabled="saving || lines.length === 0" disabled
            class="w-full rounded-xl bg-green-600 py-3 font-bold text-white hover:bg-green-700 disabled:bg-slate-300">
        <span x-show="!saving">Save delivery</span>
        <span x-show="saving" x-cloak>Saving...</span>
    </button>
</div>
