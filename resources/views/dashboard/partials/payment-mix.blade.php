@php
    // Categorical slots in fixed order (validated palette): cash, M-Pesa, credit; other in neutral grey
    $methods = [
        'cash' => ['Cash', '#2a78d6'],
        'mpesa' => ['M-Pesa', '#eb6834'],
        'credit' => ['Credit (deni)', '#1baf7a'],
        'other' => ['Card / other', '#94a3b8'],
    ];
    $sum = array_sum($payments);
@endphp
<section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <h2 class="font-semibold text-slate-900">{{ $mixTitle ?? 'How customers paid today' }}</h2>
    <p class="mb-4 text-sm text-slate-500">KSh {{ number_format($sum) }} received</p>

    @if ($sum > 0)
        <div class="mb-5 flex h-3 gap-0.5 overflow-hidden rounded-full" role="img" aria-label="Share of payments by method">
            @foreach ($methods as $key => [$label, $color])
                @if ($payments[$key] > 0)
                    <div title="{{ $label }}: KSh {{ number_format($payments[$key]) }}" style="width: {{ $payments[$key] / $sum * 100 }}%; background: {{ $color }}"></div>
                @endif
            @endforeach
        </div>
    @endif

    <dl class="space-y-3">
        @foreach ($methods as $key => [$label, $color])
            @if ($key !== 'other' || $payments['other'] > 0)
                <div class="flex items-center justify-between text-sm">
                    <dt class="flex items-center gap-2 text-slate-600">
                        <span class="h-2.5 w-2.5 rounded-sm" style="background: {{ $color }}"></span>{{ $label }}
                    </dt>
                    <dd class="tabular font-semibold text-slate-900">
                        {{ number_format($payments[$key]) }}
                        <span class="ml-1 w-10 font-normal text-slate-400">{{ $sum > 0 ? round($payments[$key] / $sum * 100) : 0 }}%</span>
                    </dd>
                </div>
            @endif
        @endforeach
    </dl>
</section>
