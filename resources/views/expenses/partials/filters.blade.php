<form method="GET" action="{{ route('expenses.index') }}" class="mb-4 flex flex-wrap items-end gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
    <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">From</label>
        <input name="from" type="date" value="{{ $from }}" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2">
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">To</label>
        <input name="to" type="date" value="{{ $to }}" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2">
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Category</label>
        <select name="category" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2">
            <option value="">All categories</option>
            @foreach (\App\Models\Expense::CATEGORIES as $key => $label)
                <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex gap-2 text-sm">
        <a href="{{ route('expenses.index', ['from' => now()->toDateString(), 'to' => now()->toDateString()]) }}" class="rounded-lg bg-slate-100 px-3 py-2 hover:bg-slate-200">Today</a>
        <a href="{{ route('expenses.index', ['from' => now()->startOfWeek()->toDateString(), 'to' => now()->toDateString()]) }}" class="rounded-lg bg-slate-100 px-3 py-2 hover:bg-slate-200">This week</a>
        <a href="{{ route('expenses.index') }}" class="rounded-lg bg-slate-100 px-3 py-2 hover:bg-slate-200">This month</a>
    </div>
</form>
