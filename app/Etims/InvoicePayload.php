<?php

namespace App\Etims;

use App\Models\EtimsInvoice;
use App\Models\SaleItem;
use App\Models\TaxRate;

/**
 * Builds the invoice / credit note in KRA's OSCU format (TIS spec v2.0, trnsSales/saveSales).
 * Prices in this POS include VAT, so taxable amounts are VAT-inclusive and tax is the VAT part of them.
 */
class InvoicePayload
{
    public const TAX_TYPES = ['A', 'B', 'C', 'D', 'E'];

    public static function build(EtimsInvoice $invoice): array
    {
        $sale = $invoice->sale()->with(['items.product', 'payments', 'customer', 'cashier'])->first();
        $isCredit = $invoice->type === 'credit_note';
        $taxCodes = TaxRate::pluck('code', 'id');

        // A credit note lists only what came back, at the refunded amounts
        $lines = $isCredit && $invoice->saleReturn
            ? $invoice->saleReturn->items()->with('saleItem.product')->get()->map(fn ($ri) => [
                'item' => $ri->saleItem,
                'qty' => (float) $ri->quantity,
                'total' => (float) $ri->refund_amount,
            ])
            : $sale->items->map(fn (SaleItem $i) => ['item' => $i, 'qty' => (float) $i->quantity, 'total' => (float) $i->line_total]);

        $buckets = array_fill_keys(self::TAX_TYPES, ['taxable' => 0.0, 'tax' => 0.0, 'rate' => 0.0]);
        $itemList = [];

        foreach ($lines->values() as $seq => $line) {
            /** @var SaleItem $item */
            $item = $line['item'];
            $type = $taxCodes[$item->product?->tax_rate_id] ?? 'B';
            $rate = (float) $item->tax_rate;
            $total = round($line['total'], 2);
            $tax = $rate > 0 ? round($total * $rate / (100 + $rate), 2) : 0.0;

            $buckets[$type]['taxable'] += $total;
            $buckets[$type]['tax'] += $tax;
            $buckets[$type]['rate'] = $rate;

            $itemList[] = [
                'itemSeq' => $seq + 1,
                'itemCd' => $item->product?->etims_item_code,
                'itemClsCd' => $item->product?->etims_class_code,
                'itemNm' => mb_substr($item->product_name . ($item->unit_name ? " ({$item->unit_name})" : ''), 0, 200),
                'pkgUnitCd' => 'NT',
                'pkg' => 1,
                'qtyUnitCd' => 'U',
                'qty' => round($line['qty'], 3),
                'prc' => $line['qty'] > 0 ? round($total / $line['qty'], 2) : 0,
                'splyAmt' => $total,
                'dcRt' => 0,
                'dcAmt' => 0,
                'taxTyCd' => $type,
                'taxblAmt' => $total,
                'taxAmt' => $tax,
                'totAmt' => $total,
            ];
        }

        // A credit note without a return reverses the whole sale (a void after KRA had the invoice)
        $when = $isCredit ? ($invoice->saleReturn?->returned_at ?? $invoice->created_at) : $sale->sold_at;
        $payload = [
            'tin' => config('etims.pin'),
            'bhfId' => config('etims.branch_id'),
            'invcNo' => $invoice->invoice_no,
            'orgInvcNo' => $isCredit ? $invoice->original_invoice_no : 0,
            'custTin' => $sale->customer?->kra_pin,
            'custNm' => $sale->customer?->name,
            'salesTyCd' => 'N',
            'rcptTyCd' => $isCredit ? 'R' : 'S',
            'pmtTyCd' => self::paymentType($sale->payments->pluck('method')->unique()->values()->all()),
            'salesSttsCd' => '02',
            'cfmDt' => $when->format('YmdHis'),
            'salesDt' => $when->format('Ymd'),
            'stockRlsDt' => $when->format('YmdHis'),
            'rfdDt' => $isCredit ? $when->format('YmdHis') : null,
            'rfdRsnCd' => $isCredit ? '06' : null, // 06 = refund (KRA refund reason code)
            'totItemCnt' => count($itemList),
            'totTaxblAmt' => round(array_sum(array_column($buckets, 'taxable')), 2),
            'totTaxAmt' => round(array_sum(array_column($buckets, 'tax')), 2),
            'totAmt' => round(array_sum(array_column($itemList, 'totAmt')), 2),
            'prchrAcptcYn' => 'N',
            'remark' => $isCredit ? ($invoice->saleReturn ? 'Return ' . $invoice->saleReturn->return_no : 'Void ' . $sale->receipt_no) : $sale->receipt_no,
            'regrId' => $sale->user_id,
            'regrNm' => $sale->cashier?->name,
            'modrId' => $sale->user_id,
            'modrNm' => $sale->cashier?->name,
            'receipt' => [
                'custTin' => $sale->customer?->kra_pin,
                'custMblNo' => $sale->customer?->phone,
                'rptNo' => $invoice->invoice_no,
                'trdeNm' => config('app.name'),
                'adrs' => $sale->branch?->address,
                'topMsg' => config('app.name'),
                'btmMsg' => 'Thank you',
                'prchrAcptcYn' => 'N',
            ],
            'itemList' => $itemList,
        ];

        foreach (self::TAX_TYPES as $code) {
            $payload["taxblAmt{$code}"] = round($buckets[$code]['taxable'], 2);
            $payload["taxRt{$code}"] = $buckets[$code]['rate'];
            $payload["taxAmt{$code}"] = round($buckets[$code]['tax'], 2);
        }

        return $payload;
    }

    /** KRA payment type codes: 01 cash, 02 credit, 03 cash/credit, 05 card, 06 mobile money, 07 other. */
    public static function paymentType(array $methods): string
    {
        sort($methods);

        return match (true) {
            $methods === ['cash'] => '01',
            $methods === ['credit'] => '02',
            $methods === ['cash', 'credit'] => '03',
            $methods === ['card'] => '05',
            $methods === ['mpesa'] => '06',
            default => '07',
        };
    }
}
