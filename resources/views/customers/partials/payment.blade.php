<p class="-mt-3 mb-4 text-sm text-slate-500">{{ $paying->name }} owes <b class="text-red-600">{{ number_format($paying->balance, 2) }}</b></p>
<form method="POST" action="{{ route('customers.payment', $paying) }}" class="space-y-3"
      x-data="{ amount: @js((string) old('payAmount', '')), method: @js(old('payMethod', 'cash')) }">
    @csrf
    <div>
        <label class="mb-1 block text-sm font-medium">Amount</label>
        <div class="flex gap-2">
            <input name="payAmount" x-model="amount" type="number" step="0.01" min="0" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2">
            <button type="button" x-on:click="amount = @js((string) (float) $paying->balance)" class="rounded-lg bg-slate-100 px-3 text-sm font-semibold hover:bg-slate-200">All</button>
        </div>
        @include('partials.error', ['field' => 'payAmount'])
    </div>
    <div class="flex gap-4 text-sm">
        <label class="flex items-center gap-1"><input name="payMethod" x-model="method" type="radio" value="cash"> Cash</label>
        <label class="flex items-center gap-1"><input name="payMethod" x-model="method" type="radio" value="mpesa"> M-Pesa</label>
    </div>
    @error('payMethod') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    <template x-if="method === 'mpesa'">
        <div>
            <input name="payReference" value="{{ old('payReference') }}" type="text" placeholder="M-Pesa code" class="w-full rounded-lg border border-slate-300 px-3 py-2 uppercase">
            @include('partials.error', ['field' => 'payReference'])
        </div>
    </template>
    <div class="flex justify-end gap-2">
        <a href="{{ $listUrl }}" class="rounded-lg border border-slate-300 px-4 py-2 hover:bg-slate-50">Cancel</a>
        <button type="submit" class="rounded-lg bg-green-600 px-4 py-2 font-semibold text-white hover:bg-green-700">Record payment</button>
    </div>
</form>
