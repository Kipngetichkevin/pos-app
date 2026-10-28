<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shift report</title>
    <style>
        body { font-family: "Courier New", monospace; font-size: 12px; width: 72mm; margin: 0 auto; padding: 4mm 0; color: #000; }
        .c { text-align: center; } .r { text-align: right; } .b { font-weight: bold; } .big { font-size: 15px; }
        table { width: 100%; border-collapse: collapse; } td { padding: 1px 0; vertical-align: top; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .btns { margin-top: 12px; display: flex; gap: 6px; }
        .btns a, .btns button { flex: 1; padding: 8px; font-size: 14px; text-align: center; cursor: pointer; border: 1px solid #999; background: #f5f5f5; color: #000; text-decoration: none; }
        @media print { .btns { display: none; } @page { margin: 0; } }
    </style>
</head>
<body>
    <div class="c b big">{{ config('app.name') }}</div>
    <div class="c">{{ $shift->branch?->name }}</div>
    <div class="c b">SHIFT REPORT</div>
    <hr>
    <div>Till: {{ $shift->till?->code }} - {{ $shift->till?->name }}</div>
    <div>Cashier: {{ $shift->user?->name }}</div>
    <div>Opened: {{ $shift->opened_at->format('d/m/Y H:i') }}</div>
    <div>Closed: {{ $shift->closed_at?->format('d/m/Y H:i') ?? 'STILL OPEN' }}</div>
    @if ($shift->closedBy && $shift->closed_by !== $shift->user_id)
        <div>Closed by: {{ $shift->closedBy->name }}</div>
    @endif
    <hr>
    <table>
        <tr><td>Sales</td><td class="r">{{ $summary['sales_count'] }}</td></tr>
        <tr><td>Total sales</td><td class="r">{{ number_format($summary['sales_total'], 2) }}</td></tr>
        <tr><td>M-Pesa</td><td class="r">{{ number_format($summary['mpesa'], 2) }}</td></tr>
        @if ($summary['other'] > 0)
            <tr><td>Card / other</td><td class="r">{{ number_format($summary['other'], 2) }}</td></tr>
        @endif
        @if ($summary['credit'] > 0)
            <tr><td>On credit (deni)</td><td class="r">{{ number_format($summary['credit'], 2) }}</td></tr>
        @endif
        <tr><td>Cash sales</td><td class="r">{{ number_format($summary['cash'], 2) }}</td></tr>
        @if ($summary['voided'] > 0)
            <tr><td>Voided sales</td><td class="r">{{ $summary['voided'] }}</td></tr>
        @endif
        @if ($summary['unverified'] > 0)
            <tr><td>Unverified M-Pesa</td><td class="r">{{ $summary['unverified'] }}</td></tr>
        @endif
    </table>
    <hr>
    <table>
        <tr><td>Opening float</td><td class="r">{{ number_format($summary['opening_float'], 2) }}</td></tr>
        <tr><td>+ Cash sales</td><td class="r">{{ number_format($summary['cash'], 2) }}</td></tr>
        @if ($summary['cash_refunds'] > 0)
            <tr><td>- Cash refunds</td><td class="r">{{ number_format($summary['cash_refunds'], 2) }}</td></tr>
        @endif
        @if ($summary['debt_cash'] > 0)
            <tr><td>+ Debt payments</td><td class="r">{{ number_format($summary['debt_cash'], 2) }}</td></tr>
        @endif
        @if (($summary['cash_expenses'] ?? 0) > 0)
            <tr><td>- Cash expenses</td><td class="r">{{ number_format($summary['cash_expenses'], 2) }}</td></tr>
        @endif
        <tr class="b"><td>Expected cash</td><td class="r">{{ number_format($shift->expected_cash ?? $summary['expected_cash'], 2) }}</td></tr>
        @if ($shift->counted_cash !== null)
            <tr class="b"><td>Counted cash</td><td class="r">{{ number_format($shift->counted_cash, 2) }}</td></tr>
            <tr class="b big">
                <td>{{ (float) $shift->variance == 0 ? 'BALANCED' : ((float) $shift->variance < 0 ? 'SHORTAGE' : 'EXCESS') }}</td>
                <td class="r">{{ number_format(abs($shift->variance), 2) }}</td>
            </tr>
        @endif
    </table>
    @if ($shift->notes)
        <hr>
        <div>Remarks: {{ $shift->notes }}</div>
    @endif
    <hr>
    <div style="margin-top: 18px;">Cashier sign: ______________</div>
    <div style="margin-top: 14px;">Manager sign: ______________</div>

    <div class="btns">
        <button onclick="window.print()">Print</button>
        <a href="{{ route('dashboard') }}">Dashboard</a>
    </div>
</body>
</html>