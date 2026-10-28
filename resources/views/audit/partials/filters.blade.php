<form method="GET" action="{{ route('audit.index') }}" class="mb-4 flex flex-wrap items-end gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
    <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">From</label>
        <input name="from" type="date" value="{{ $from }}" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2">
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">To</label>
        <input name="to" type="date" value="{{ $to }}" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2">
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">What</label>
        <select name="action" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2">
            <option value="">Everything</option>
            @foreach (\App\Models\AuditLog::ACTIONS as $key => $label)
                <option value="{{ $key }}" @selected($action === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Who</label>
        <select name="user" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2">
            <option value="">Anyone</option>
            @foreach ($users as $u)
                <option value="{{ $u->id }}" @selected($userId === $u->id)>{{ $u->name }}</option>
            @endforeach
        </select>
    </div>
</form>
