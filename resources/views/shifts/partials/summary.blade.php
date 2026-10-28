{{-- Shift summary --}}
<div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <div class="mb-3 text-sm text-slate-500">
        Till {{ $shift->till->code }} &middot; {{ $shift->user->name }}<br>
        Opened {{ $shift->opened_at->format('d M Y, H:i') }}
    </div>

    <dl class="space-y-2 text-sm">
        <div class="flex justify-between"><dt>Sales</dt><dd class="font-semibold">{{ $summary['sales_count'] }}</dd></div>
        <div class="flex justify-between"><dt>Total sales</dt><dd class="font-semibold">{{ number_format($summary['sales_total'], 2) }}</dd></div>
        <div class="flex justify-between"><dt>M-Pesa</dt><dd class="font-semibold text-green-700">{{ number_format($summary['mpesa'], 2) }}</dd></div>
        @if ($summary['other'] > 0)
            <div class="flex justify-between"><dt>Card / other</dt><dd class="font-semibold">{{ number_format($summary['other'], 2) }}</dd></div>
        @endif
        @if ($summary['credit'] > 0)<div class="flex justify-between"><dt>On credit (deni)</dt><dd class="font-semibold text-amber-600">{{ number_format($summary['credit'], 2) }}</dd></div>@endif
        <div class="border-t border-slate-200 pt-2"></div>
        <div class="flex justify-between"><dt>Opening float</dt><dd>{{ number_format($summary['opening_float'], 2) }}</dd></div>
        <div class="flex justify-between"><dt>+ Cash sales</dt><dd>{{ number_format($summary['cash'], 2) }}</dd></div>@if ($summary['cash_refunds'] > 0)<div class="flex justify-between text-red-600"><dt>- Cash refunds</dt><dd>{{ number_format($summary['cash_refunds'], 2) }}</dd></div>@endif
        @if ($summary['debt_cash'] > 0)<div class="flex justify-between text-green-700"><dt>+ Debt payments (cash)</dt><dd>{{ number_format($summary['debt_cash'], 2) }}</dd></div>@endif
        @if ($summary['cash_expenses'] > 0)<div class="flex justify-between text-red-600"><dt>- Cash expenses</dt><dd>{{ number_format($summary['cash_expenses'], 2) }}</dd></div>@endif
        <div class="flex justify-between text-base font-bold"><dt>Expected cash in drawer</dt><dd>{{ number_format($summary['expected_cash'], 2) }}</dd></div>
    </dl>

    @if ($summary['unverified'] > 0)
        <div class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
            {{ $summary['unverified'] }} M-Pesa payment(s) on this shift are unverified. A manager can check them on the Sales &amp; M-Pesa screen.
        </div>
    @endif
</div>
