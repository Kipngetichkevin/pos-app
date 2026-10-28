<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt {{ $sale->receipt_no }}</title>
    @vite(['resources/js/app.js'])
    <style>
        body { font-family: "Courier New", monospace; font-size: 12px; width: 72mm; margin: 0 auto; padding: 4mm 0; color: #000; }
        .c { text-align: center; } .r { text-align: right; } .b { font-weight: bold; } .big { font-size: 15px; }
        table { width: 100%; border-collapse: collapse; } td { vertical-align: top; padding: 1px 0; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        .btns { margin-top: 12px; display: flex; gap: 6px; }
        .btns button { flex: 1; padding: 8px; font-size: 14px; cursor: pointer; }
        @@media print { .btns { display: none; } @@page { margin: 0; } }
    </style>
</head>
<body>
    <div class="c b big">{{ config('app.name') }}</div>
    <div class="c">{{ $sale->branch->name }}</div>
    @if ($sale->branch->phone) <div class="c">Tel: {{ $sale->branch->phone }}</div> @endif
    @if ($sale->branch->kra_pin) <div class="c">PIN: {{ $sale->branch->kra_pin }}</div> @endif
    <hr>
    <div>Receipt: {{ $sale->receipt_no }}</div>
    <div>Date: {{ $sale->sold_at->format('d/m/Y H:i') }}</div>
    <div>Cashier: {{ $sale->cashier->name }}</div>@if ($sale->customer)<div>Customer: {{ $sale->customer->name }} {{ $sale->customer->phone }}</div>@endif
    <hr>
    <table>
        @foreach ($sale->items as $item)
            <tr><td colspan="2">{{ $item->product_name }}{{ $item->unit_name ? ' (' . $item->unit_name . ')' : '' }}</td></tr>
            <tr>
                <td>{{ rtrim(rtrim(number_format($item->quantity, 3), '0'), '.') }} x {{ number_format($item->unit_price, 2) }}</td>
                <td class="r">{{ number_format($item->line_total, 2) }}</td>
            </tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr class="b big"><td>TOTAL</td><td class="r">{{ number_format($sale->grand_total, 2) }}</td></tr>
        @foreach ($sale->payments as $payment)
            <tr>
                <td>{{ strtoupper($payment->method) }} {{ $payment->reference }}</td>
                <td class="r">{{ number_format($payment->amount, 2) }}</td>
            </tr>
        @endforeach
        <tr><td>Amount paid</td><td class="r">{{ number_format($sale->amount_paid, 2) }}</td></tr>
        <tr><td>Change</td><td class="r">{{ number_format($sale->change_given, 2) }}</td></tr>
        @if ($sale->customer && $sale->payments->where('method', 'credit')->isNotEmpty())<tr class="b"><td>Balance owed now</td><td class="r">{{ number_format($sale->customer->balance, 2) }}</td></tr>@endif<tr><td>VAT included</td><td class="r">{{ number_format($sale->tax_total, 2) }}</td></tr>
    </table>
    @include('receipts.partials.etims', ['invoice' => $sale->etimsInvoice])
    <hr>
    <div class="c">Thank you for shopping with us!</div>
    <div class="c">Goods once sold are not returnable without a receipt.</div>

    <div class="btns">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </div>

    <script>
        window.onload = async () => {
            const qr = document.getElementById('etims-qr');
            if (qr && window.QRCode) {
                try { await QRCode.toCanvas(qr, qr.dataset.url, { width: 140, margin: 0 }); } catch (e) {}
            }
            window.print();
        };
    </script>
</body>
</html>


