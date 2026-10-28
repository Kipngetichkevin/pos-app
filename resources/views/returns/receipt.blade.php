<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Return {{ $return->return_no }}</title>
    @vite(['resources/js/app.js'])
    <style>
        body { font-family: "Courier New", monospace; font-size: 12px; width: 72mm; margin: 0 auto; padding: 4mm 0; color: #000; }
        .c { text-align: center; } .r { text-align: right; } .b { font-weight: bold; } .big { font-size: 15px; }
        table { width: 100%; border-collapse: collapse; } td { padding: 1px 0; vertical-align: top; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .btns { margin-top: 12px; display: flex; gap: 6px; }
        .btns button { flex: 1; padding: 8px; font-size: 14px; cursor: pointer; }
        @@media print { .btns { display: none; } @@page { margin: 0; } }
    </style>
</head>
<body>
    <div class="c b big">{{ config('app.name') }}</div>
    <div class="c">{{ $return->sale->branch?->name }}</div>
    <div class="c b">RETURN / REFUND</div>
    <hr>
    <div>Return: {{ $return->return_no }}</div>
    <div>Original receipt: {{ $return->sale->receipt_no }}</div>
    <div>Date: {{ $return->returned_at->format('d/m/Y H:i') }}</div>
    <div>Processed by: {{ $return->user?->name }}</div>
    <div>Approved by: {{ $return->approvedBy?->name }}</div>
    <hr>
    <table>
        @foreach ($return->items as $ri)
            <tr><td colspan="2">{{ $ri->saleItem?->product_name }}{{ $ri->saleItem?->unit_name ? ' (' . $ri->saleItem->unit_name . ')' : '' }}</td></tr>
            <tr>
                <td>{{ rtrim(rtrim(number_format((float) $ri->quantity, 3), '0'), '.') }} x {{ number_format((float) $ri->saleItem?->unit_price, 2) }}{{ $ri->restock ? '' : ' (damaged)' }}</td>
                <td class="r">{{ number_format((float) $ri->refund_amount, 2) }}</td>
            </tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr class="b big"><td>REFUND</td><td class="r">{{ number_format((float) $return->total_refunded, 2) }}</td></tr>
        <tr><td>Paid by</td><td class="r">{{ strtoupper($return->refund_method) }} {{ $return->refund_reference }}</td></tr>
    </table>
    <hr>
    <div>Reason: {{ $return->reason }}</div>
    @include('receipts.partials.etims', ['invoice' => $creditNote])
    <div style="margin-top: 16px;">Customer sign: ______________</div>

    <script>
        window.onload = async () => {
            const qr = document.getElementById('etims-qr');
            if (qr && window.QRCode) {
                try { await QRCode.toCanvas(qr, qr.dataset.url, { width: 140, margin: 0 }); } catch (e) {}
            }
        };
    </script>
    <div class="btns">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </div>
</body>
</html>
