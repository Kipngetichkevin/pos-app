<form method="POST" action="{{ route('expenses.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2" x-data="{ method: @js(old('method', 'cash')) }">
    @csrf

    <div>
        <label class="mb-1 block text-sm font-medium">Category *</label>
        <select name="category" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            <option value="">-- Choose --</option>
            @foreach (\App\Models\Expense::CATEGORIES as $key => $label)
                <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @include('partials.error', ['field' => 'category'])
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Amount (KSh) *</label>
        <input name="amount" value="{{ old('amount') }}" type="number" step="0.01" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        @include('partials.error', ['field' => 'amount'])
    </div>

    <div class="md:col-span-2">
        <label class="mb-1 block text-sm font-medium">What was it for? *</label>
        <input name="description" value="{{ old('description') }}" type="text" placeholder="e.g. KPLC tokens, boda boda to supplier" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        @include('partials.error', ['field' => 'description'])
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Paid to</label>
        <input name="paid_to" value="{{ old('paid_to') }}" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        @include('partials.error', ['field' => 'paid_to'])
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Paid by *</label>
        <select name="method" x-model="method" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            @foreach (\App\Models\Expense::METHODS as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        @include('partials.error', ['field' => 'method'])
        <p x-show="method === 'cash'" class="mt-1 text-xs text-slate-500">
            @if ($shift)
                Taken from till {{ $shift->till?->code }}: the cash-up will expect this much less.
            @else
                You have no open shift. Open one on the POS to pay cash from the till.
            @endif
        </p>
    </div>

        <div class="md:col-span-2" x-show="method !== 'cash'" x-cloak x-data="{ 
        checking: false, 
        verified: false, 
        msg: '', 
        isError: false,
        checkCode() {
            const code = $refs.codeInput.value.trim().toUpperCase();
            if (code.length < 10) return;
            this.checking = true;
            this.verified = false;
            this.msg = '';
            fetch(`/expenses/verify-mpesa?code=${code}`)
                .then(res => res.json())
                .then(data => {
                    this.checking = false;
                    this.verified = data.valid;
                    this.isError = !data.valid;
                    this.msg = data.message;
                    if (data.valid) {
                        document.querySelector('input[name="amount"]').value = data.amount; // Auto-fill amount
                    }
                })
                .catch(() => {
                    this.checking = false;
                    this.isError = true;
                    this.msg = 'Error checking code.';
                });
        }
    }">
        <label class="mb-1 block text-sm font-medium">Reference <span x-show="method === 'mpesa'">*</span></label>
        <div class="flex gap-2">
            <input name="reference" x-ref="codeInput" x-on:input="verified = false; isError = false;" value="{{ old('reference') }}" type="text" placeholder="M-Pesa code or cheque number" class="w-full rounded-lg border border-slate-300 px-3 py-2 uppercase">
            
            <button type="button" x-show="method === 'mpesa'" x-on:click="checkCode()" :disabled="checking" class="rounded-lg bg-slate-800 px-4 py-2 font-semibold text-white hover:bg-slate-900 disabled:opacity-50">
                <span x-show="!checking">Verify</span>
                <span x-show="checking" x-cloak>Checking...</span>
            </button>
        </div>
        
        <div x-show="msg" x-cloak class="mt-1 text-sm font-medium" :class="isError ? 'text-red-600' : 'text-green-600'" x-text="msg"></div>
        
        @include('partials.error', ['field' => 'reference'])
    </div>

@if (auth()->user()->isManager())
        <div class="md:col-span-2">
            <label class="mb-1 block text-sm font-medium">Date (leave blank for now)</label>
            <input name="spent_at" value="{{ old('spent_at') }}" type="datetime-local" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            @include('partials.error', ['field' => 'spent_at'])
        </div>
    @endif

    <div class="flex justify-end gap-2 md:col-span-2">
        <a href="{{ route('expenses.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 hover:bg-slate-50">Cancel</a>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">Save expense</button>
    </div>
</form>

