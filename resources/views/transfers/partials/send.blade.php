<div class="h-fit space-y-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
    <div>
        <label class="mb-1 block text-sm font-medium">Send to *</label>
        <select x-model="to_branch_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            <option value="">-- Choose branch --</option>
            @foreach ($branches as $b)
                <option value="{{ $b->id }}">{{ $b->name }} ({{ $b->code }})</option>
            @endforeach
        </select>
        <p x-show="errors.to_branch_id" x-text="errors.to_branch_id" x-cloak class="mt-1 text-sm text-red-600"></p>
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium">Notes</label>
        <input x-model="notes" type="text" placeholder="e.g. Driver: Otieno, KCX 123A" class="w-full rounded-lg border border-slate-300 px-3 py-2">
    </div>
    <p x-show="errors.general" x-text="errors.general" x-cloak class="rounded-lg bg-red-50 p-3 text-sm text-red-700"></p>
    <button type="button" x-on:click="send()" :disabled="busy || ! Object.keys(lines).length"
            class="w-full rounded-xl bg-blue-600 py-3 font-bold text-white hover:bg-blue-700 disabled:bg-slate-300">
        <span x-text="busy ? 'Sending...' : 'Send stock'">Send stock</span>
    </button>
    <p class="text-xs text-slate-500">The stock leaves this branch now. It is added to the other branch when they confirm it arrived.</p>
</div>
