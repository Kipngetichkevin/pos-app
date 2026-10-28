@php
    $fq = fn ($q) => rtrim(rtrim(number_format((float) $q, 3), '0'), '.');
    $by = request('by') === 'qty' ? 'qty' : 'revenue';
    $max = $top->max($by) ?: 1;
    $catMax = $categories->max('revenue') ?: 1;
@endphp

<div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 lg:col-span-2">
        <div class="flex flex-wrap items-center justify-between gap-2 px-5 pt-5">
            <div>
                <h2 class="font-semibold text-slate-900">Best sellers</h2>
                <p class="text-sm text-slate-500">Top {{ $top->count() }} products</p>
            </div>
            <div class="inline-flex rounded-lg bg-slate-100 p-0.5 text-sm print:hidden">
                <a href="{{ request()->fullUrlWithQuery(['by' => 'revenue']) }}" class="rounded-md px-3 py-1 {{ $by === 'revenue' ? 'bg-white font-semibold shadow-sm' : 'text-slate-500' }}">By money</a>
                <a href="{{ request()->fullUrlWithQuery(['by' => 'qty']) }}" class="rounded-md px-3 py-1 {{ $by === 'qty' ? 'bg-white font-semibold shadow-sm' : 'text-slate-500' }}">By quantity</a>
            </div>
        </div>
        <table class="mt-4 w-full text-left text-sm">
            <thead class="border-y border-slate-100 bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-5 py-2.5">Product</th>
                    <th class="px-3 py-2.5 text-right">Sold</th>
                    <th class="px-3 py-2.5 text-right">Sales</th>
                    <th class="px-3 py-2.5 text-right">Profit</th>
                    <th class="px-5 py-2.5 text-right">Margin</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($top as $i => $p)
                    <tr>
                        <td class="px-5 py-2.5">
                            <div class="font-medium text-slate-800"><span class="mr-2 text-slate-400">{{ $i + 1 }}</span>{{ $p['name'] }}</div>
                            <div class="mt-1 h-1 rounded-full bg-slate-100"><div class="h-1 rounded-full bg-indigo-500" style="width: {{ $p[$by] / $max * 100 }}%"></div></div>
                        </td>
                        <td class="px-3 py-2.5 text-right">{{ $fq($p['qty']) }}</td>
                        <td class="px-3 py-2.5 text-right font-semibold text-slate-900">{{ number_format($p['revenue']) }}</td>
                        <td class="px-3 py-2.5 text-right">{{ number_format($p['profit']) }}</td>
                        <td class="px-5 py-2.5 text-right {{ $p['margin'] < 10 ? 'font-semibold text-rose-600' : 'text-slate-600' }}">{{ $p['margin'] }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">No sales in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="space-y-4">
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="font-semibold text-slate-900">By category</h2>
            <p class="mb-4 text-sm text-slate-500">Sales and margin</p>
            <div class="space-y-3">
                @foreach ($categories as $c)
                    <div title="{{ $c['name'] }}: KSh {{ number_format($c['revenue']) }}, profit {{ number_format($c['profit']) }}">
                        <div class="mb-1 flex justify-between text-sm">
                            <span class="text-slate-700">{{ $c['name'] }}</span>
                            <span><b class="tabular text-slate-900">{{ number_format($c['revenue']) }}</b> <span class="text-xs text-slate-400">{{ $c['margin'] }}%</span></span>
                        </div>
                        <div class="h-1.5 rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-indigo-500" style="width: {{ $c['revenue'] / $catMax * 100 }}%"></div></div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <h2 class="font-semibold text-slate-900">Not selling</h2>
            <p class="mb-3 text-sm text-slate-500">In stock but no sales in this period: money sitting on the shelf</p>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($slow as $s)
                    <li class="flex justify-between py-2">
                        <span class="text-slate-700">{{ $s['name'] }} <span class="text-xs text-slate-400">&middot; {{ $fq($s['qty']) }} left</span></span>
                        <span class="tabular font-semibold text-slate-900">{{ number_format($s['value']) }}</span>
                    </li>
                @empty
                    <li class="py-4 text-center text-slate-400">Everything in stock sold at least once.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
