{{-- Summary cards --}}
<div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-7">
    @foreach ([
        ['Sales', number_format($summary['count']), 'text-slate-900'],
        ['Total sales', number_format($summary['total'], 2), 'text-slate-900'],
        ['Cash', number_format($summary['cash'], 2), 'text-slate-900'],
        ['M-Pesa', number_format($summary['mpesa'], 2), 'text-green-700'],
        ['Refunds', number_format($summary['refunds'], 2), 'text-red-600'],
        ['VAT included', number_format($summary['tax'], 2), 'text-slate-900'],
        ['Gross profit', number_format($summary['profit'], 2), 'text-blue-700'],
    ] as [$label, $value, $color])
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <div class="text-xs uppercase text-slate-500">{{ $label }}</div>
            <div class="mt-1 text-xl font-bold {{ $color }}">{{ $value }}</div>
        </div>
    @endforeach
</div>
