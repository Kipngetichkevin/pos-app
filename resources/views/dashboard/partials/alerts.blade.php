@php
    $tones = [
        'red' => 'bg-rose-50 text-rose-700 ring-rose-200',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-200',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    ];
@endphp
<section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <h2 class="font-semibold text-slate-900">Needs your attention</h2>
    <p class="mb-4 text-sm text-slate-500">Tap one to sort it out</p>

    <div class="space-y-2">
        @forelse ($alerts as $alert)
            <a href="{{ $alert['url'] }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 ring-1 ring-inset {{ $tones[$alert['tone']] }} hover:brightness-95">
                @include('partials.icon', ['name' => $alert['icon'], 'class' => 'h-5 w-5 shrink-0'])
                <span class="flex-1 text-sm"><b class="tabular">{{ $alert['count'] }}</b> {{ $alert['label'] }}</span>
                @include('partials.icon', ['name' => 'chevron-right', 'class' => 'h-4 w-4 opacity-60'])
            </a>
        @empty
            <div class="flex flex-col items-center gap-2 py-8 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                    @include('partials.icon', ['name' => 'check-circle', 'class' => 'h-7 w-7'])
                </span>
                <span class="text-sm font-medium text-slate-700">All clear</span>
                <span class="text-xs text-slate-400">Nothing needs attention right now.</span>
            </div>
        @endforelse
    </div>
</section>
