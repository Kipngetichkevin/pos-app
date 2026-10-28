<section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="px-5 pt-5">
        <h2 class="font-semibold text-slate-900">VAT summary</h2>
        <p class="text-sm text-slate-500">Sales by KRA tax code, after refunds. Use these figures for the VAT3 return on iTax.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="mt-4 w-full text-left text-sm">
            <thead class="border-y border-slate-100 bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-5 py-2.5">Tax code</th>
                    <th class="px-3 py-2.5 text-right">Sales incl. VAT</th>
                    <th class="px-3 py-2.5 text-right">Refunds</th>
                    <th class="px-3 py-2.5 text-right">Net incl. VAT</th>
                    <th class="px-3 py-2.5 text-right">Net excl. VAT</th>
                    <th class="px-5 py-2.5 text-right">VAT</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($vat as $v)
                    <tr class="{{ $v['net_gross'] == 0 ? 'text-slate-400' : '' }}">
                        <td class="px-5 py-3"><span class="mr-2 inline-flex h-6 w-6 items-center justify-center rounded bg-slate-100 text-xs font-bold text-slate-700">{{ $v['code'] }}</span>{{ $v['name'] }}</td>
                        <td class="px-3 py-3 text-right">{{ number_format($v['gross'], 2) }}</td>
                        <td class="px-3 py-3 text-right">{{ $v['refund_gross'] ? '(' . number_format($v['refund_gross'], 2) . ')' : '-' }}</td>
                        <td class="px-3 py-3 text-right font-semibold">{{ number_format($v['net_gross'], 2) }}</td>
                        <td class="px-3 py-3 text-right">{{ number_format($v['net_ex_vat'], 2) }}</td>
                        <td class="px-5 py-3 text-right font-semibold text-slate-900">{{ number_format($v['net_tax'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="border-t-2 border-slate-200 font-bold text-slate-900">
                <tr>
                    <td class="px-5 py-3">Total</td>
                    <td class="px-3 py-3 text-right">{{ number_format($vat->sum('gross'), 2) }}</td>
                    <td class="px-3 py-3 text-right">({{ number_format($vat->sum('refund_gross'), 2) }})</td>
                    <td class="px-3 py-3 text-right">{{ number_format($vat->sum('net_gross'), 2) }}</td>
                    <td class="px-3 py-3 text-right">{{ number_format($vat->sum('net_ex_vat'), 2) }}</td>
                    <td class="px-5 py-3 text-right text-indigo-700">{{ number_format($vat->sum('net_tax'), 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">This is output VAT on sales only. Input VAT on purchases is claimed from your suppliers' eTIMS invoices on iTax.</p>
</section>
