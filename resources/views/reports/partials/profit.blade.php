@php
    $s = $summary;
    $row = fn ($label, $value, $class = '') => ['label' => $label, 'value' => $value, 'class' => $class];
@endphp

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 lg:col-span-2">
        <h2 class="font-semibold text-slate-900">Profit &amp; loss</h2>
        <p class="mb-5 text-sm text-slate-500">Money in, what the goods cost you, and what is left after running costs</p>

        <dl class="tabular text-sm">
            <div class="flex justify-between py-2"><dt class="text-slate-600">Sales</dt><dd>{{ number_format($s['sales'], 2) }}</dd></div>
            <div class="flex justify-between py-2"><dt class="text-slate-600">Less refunds</dt><dd class="text-rose-600">({{ number_format($s['refunds'], 2) }})</dd></div>
            <div class="flex justify-between border-t border-slate-200 py-2 font-semibold"><dt>Revenue</dt><dd>{{ number_format($s['revenue'], 2) }}</dd></div>
            <div class="flex justify-between py-2"><dt class="text-slate-600">Less cost of goods sold</dt><dd class="text-rose-600">({{ number_format($s['cost'], 2) }})</dd></div>
            <div class="flex justify-between rounded-lg bg-indigo-50 px-3 py-2.5 font-bold text-indigo-900"><dt>Gross profit <span class="font-normal text-indigo-600">· {{ $s['margin'] }}%</span></dt><dd>{{ number_format($s['gross_profit'], 2) }}</dd></div>

            <div class="mt-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Running costs</div>
            @forelse ($expenses as $cat => $amount)
                <div class="flex justify-between py-1.5"><dt class="text-slate-600">{{ \App\Models\Expense::CATEGORIES[$cat] ?? $cat }}</dt><dd class="text-rose-600">({{ number_format($amount, 2) }})</dd></div>
            @empty
                <div class="py-1.5 text-slate-400">No expenses recorded.</div>
            @endforelse
            <div class="flex justify-between border-t border-slate-200 py-2"><dt class="text-slate-600">Total expenses</dt><dd class="text-rose-600">({{ number_format($s['expenses'], 2) }})</dd></div>

            <div class="mt-2 flex justify-between rounded-xl px-4 py-3 text-lg font-bold {{ $s['net_profit'] >= 0 ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800' }}">
                <dt>Net {{ $s['net_profit'] >= 0 ? 'profit' : 'loss' }}</dt><dd>KSh {{ number_format($s['net_profit'], 2) }}</dd>
            </div>
        </dl>
    </section>

    <section class="h-fit rounded-2xl bg-white p-5 text-sm text-slate-600 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-2 font-semibold text-slate-900">How this is worked out</h2>
        <ul class="list-disc space-y-2 pl-5">
            <li>Prices include VAT, so revenue here includes VAT. See the VAT tab for what goes to KRA.</li>
            <li>Cost of goods uses the average buying price at the time of each sale.</li>
            <li>Voided sales are left out. Refunds count on the day they were given.</li>
            <li>Expenses come from the Expenses screen, including rent and wages paid by M-Pesa or bank.</li>
        </ul>
    </section>
</div>
