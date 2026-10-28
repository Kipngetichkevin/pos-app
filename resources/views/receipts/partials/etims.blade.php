{{-- KRA eTIMS details. Shown once KRA has signed the invoice; reprint the receipt later if it was still pending. --}}
@if ($invoice)
    <hr>
    @if ($invoice->isSent())
        <div class="c b">KRA eTIMS {{ $invoice->type === 'credit_note' ? 'CREDIT NOTE' : 'INVOICE' }}</div>
        <table>
            <tr><td>Invoice no.</td><td class="r">{{ $invoice->invoice_no }}</td></tr>
            <tr><td>CU invoice no.</td><td class="r">{{ $invoice->sdc_id }}/{{ $invoice->kra_receipt_no }}</td></tr>
            <tr><td>Date</td><td class="r">{{ $invoice->signed_at?->format('d/m/Y H:i:s') }}</td></tr>
        </table>
        <div>Internal data:</div>
        <div style="word-break: break-all;">{{ $invoice->internal_data }}</div>
        <div>Receipt signature:</div>
        <div style="word-break: break-all;">{{ $invoice->receipt_signature }}</div>
        <div class="c" style="margin-top: 6px;"><canvas id="etims-qr" data-url="{{ $invoice->qrUrl() }}"></canvas></div>
        @if (config('etims.driver') === 'fake')
            <div class="c b">*** TRAINING - NOT A TAX INVOICE ***</div>
        @endif
    @elseif ($invoice->status !== 'not_required')
        <div class="c">KRA eTIMS invoice pending.</div>
        <div class="c">Ask for a reprint to get the KRA details.</div>
    @endif
@endif
