@extends('layouts.app')

@section('title', 'Sell')

@section('content')
@include('partials.header')

@if (! $shift)
    <main class="px-4 py-10">
        @include('pos.partials.open-shift')
    </main>
@else
    <main class="p-4 lg:p-6" x-data="posTerminal(@js(['shiftId' => $shift->id, 'isManager' => $isManager]))" x-init="init()">
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_26rem] xl:items-start">
            {{-- Products --}}
            <div class="min-w-0 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Till {{ $shift->till->code }} open
                        </span>
                        <span class="text-sm text-slate-500">since {{ $shift->opened_at->format('H:i') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="hidden text-xs text-slate-400 md:inline">
                            <kbd class="rounded border border-slate-300 bg-white px-1.5 font-sans">F2</kbd> search
                            <kbd class="ml-2 rounded border border-slate-300 bg-white px-1.5 font-sans">F9</kbd> charge
                        </span>
                        <a href="{{ route('returns') }}" class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">
                            @include('partials.icon', ['name' => 'arrow-uturn-left', 'class' => 'h-4 w-4'])
                            Returns
                        </a>
                        <a href="{{ route('shifts.close', $shift) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 hover:bg-slate-50">
                            @include('partials.icon', ['name' => 'clock', 'class' => 'h-4 w-4'])
                            Close shift
                        </a>
                    </div>
                </div>

                @include('pos.partials.scan')
                @include('pos.partials.grid')
            </div>

            {{-- Current sale --}}
            <div class="xl:sticky xl:top-20">
                @include('pos.partials.sale-panel')
            </div>
        </div>

        @include('pos.partials.picker')
        @include('pos.partials.done')
    </main>

    @include('pos.partials.script')
@endif
@endsection
