{{-- Filters: dates and selects submit on change, search submits on Enter --}}
<form method="GET" action="{{ route('sales.index') }}" class="mb-4 flex flex-wrap items-end gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="flex gap-1">
        @foreach ($ranges as $label => [$rangeFrom, $rangeTo])
            <a href="{{ route('sales.index', array_merge($filters, ['from' => $rangeFrom, 'to' => $rangeTo])) }}" class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium hover:bg-slate-200">{{ $label }}</a>
        @endforeach
    </div>
    <div>
        <label class="block text-xs text-slate-500">From</label>
        <input name="from" value="{{ $from }}" type="date" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
    </div>
    <div>
        <label class="block text-xs text-slate-500">To</label>
        <input name="to" value="{{ $to }}" type="date" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
    </div>
    @if ($tab === 'sales')
        <input type="hidden" name="mpesaFilter" value="{{ $mpesaFilter }}">
        <div>
            <label class="block text-xs text-slate-500">Cashier</label>
            <select name="cashier" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                <option value="">All cashiers</option>
                @foreach ($cashiers as $user)
                    <option value="{{ $user->id }}" @selected((string) $cashier === (string) $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
    @else
        <input type="hidden" name="cashier" value="{{ $cashier }}">
        <div>
            <label class="block text-xs text-slate-500">Show</label>
            <select name="mpesaFilter" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                @foreach (['all' => 'All transactions', 'success' => 'Paid', 'unattached' => 'Paid but not on a sale', 'pending' => 'Pending', 'cancelled' => 'Cancelled', 'failed' => 'Failed'] as $value => $label)
                    <option value="{{ $value }}" @selected($mpesaFilter === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="min-w-48 flex-1">
        <label class="block text-xs text-slate-500">Search (all dates)</label>
        <input name="search" value="{{ $search }}" type="text"
               placeholder="{{ $tab === 'sales' ? 'Receipt no. or M-Pesa code' : 'M-Pesa code, phone or name' }}"
               class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
    </div>
</form>
