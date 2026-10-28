<form method="GET" action="{{ route('reports.index') }}" class="mb-4 flex flex-wrap items-center gap-2 print:hidden">
    <input type="hidden" name="tab" value="{{ $tab }}">
    @foreach ($presets as $key => [$label])
        <a href="{{ route('reports.index', ['range' => $key, 'tab' => $tab]) }}"
           class="rounded-full px-3.5 py-1.5 text-sm font-medium {{ $preset === $key ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">{{ $label }}</a>
    @endforeach
    <span class="mx-1 hidden h-6 w-px bg-slate-200 sm:block"></span>
    <div class="flex items-center gap-2 rounded-full bg-white px-3 py-1 ring-1 {{ $preset === 'custom' ? 'ring-indigo-300' : 'ring-slate-200' }}">
        @include('partials.icon', ['name' => 'calendar-days', 'class' => 'h-4 w-4 text-slate-400'])
        <input name="from" type="date" value="{{ $from->toDateString() }}" class="border-0 bg-transparent p-0 text-sm focus:shadow-none">
        <span class="text-slate-400">–</span>
        <input name="to" type="date" value="{{ $to->toDateString() }}" onchange="this.form.submit()" class="border-0 bg-transparent p-0 text-sm focus:shadow-none">
    </div>
</form>
