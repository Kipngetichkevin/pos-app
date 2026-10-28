<div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-2">
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div class="text-xs uppercase text-slate-500">M-Pesa received (all payments)</div>
        <div class="mt-1 text-xl font-bold text-green-700">KSh {{ number_format($mpesaSummary['received'], 2) }}</div>
        <div class="text-xs text-slate-500">{{ $mpesaSummary['count'] }} payment(s)</div>
    </div>
    <div class="rounded-2xl p-4 shadow-sm ring-1 {{ $mpesaSummary['unattached'] ? 'bg-amber-50 ring-amber-200' : 'bg-white ring-slate-200' }}">
        <div class="text-xs uppercase text-slate-500">Paid but not attached to a sale</div>
        <div class="mt-1 text-xl font-bold {{ $mpesaSummary['unattached'] ? 'text-amber-700' : 'text-slate-900' }}">
            KSh {{ number_format($mpesaSummary['unattachedAmount'], 2) }}
        </div>
        <div class="text-xs text-slate-500">{{ $mpesaSummary['unattached'] }} payment(s) to check</div>
    </div>
</div>
