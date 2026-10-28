{{--
    KPI tile. @include('partials.stat', ['label' => 'Sales today', 'value' => 'KSh 12,400', 'icon' => 'banknotes',
        'sub' => '34 sales', 'delta' => 12.5 (percent vs comparison, null to hide), 'deltaLabel' => 'vs yesterday'])
--}}
<div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <div class="flex items-start justify-between gap-3">
        <div class="text-sm font-medium text-slate-500">{{ $label }}</div>
        @isset($icon)
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                @include('partials.icon', ['name' => $icon, 'class' => 'h-5 w-5'])
            </span>
        @endisset
    </div>
    <div class="mt-1 text-2xl font-bold tracking-tight text-slate-900 tabular">{{ $value }}</div>
    <div class="mt-1 flex flex-wrap items-center gap-x-2 text-sm">
        @if (isset($delta) && $delta !== null)
            <span class="inline-flex items-center gap-0.5 font-semibold {{ $delta >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                @include('partials.icon', ['name' => $delta >= 0 ? 'arrow-trending-up' : 'arrow-trending-down', 'class' => 'h-4 w-4'])
                {{ $delta >= 0 ? '+' : '' }}{{ number_format($delta, 1) }}%
            </span>
            <span class="text-slate-400">{{ $deltaLabel ?? '' }}</span>
        @endif
        @isset($sub)
            <span class="text-slate-500">{{ $sub }}</span>
        @endisset
    </div>
</div>
