{{-- Top bar. The navigation lives in the sidebar (partials.sidebar, added by the layout). --}}
<header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/80 backdrop-blur">
    <div class="flex h-14 items-center justify-between gap-4 px-4 lg:px-8">
        <div class="flex items-center gap-3">
            <button type="button" x-on:click="nav = true" class="-ml-1 rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" title="Menu">
                @include('partials.icon', ['name' => 'bars-3', 'class' => 'h-6 w-6'])
            </button>
            <div class="text-sm text-slate-500">
                <span class="font-medium text-slate-700">{{ now()->format('l') }}</span>, {{ now()->format('j F Y') }}
            </div>
        </div>

        <div class="flex items-center gap-3">
            @include('partials.sync-badge')
            @if (Route::currentRouteName() !== 'pos')
                <a href="{{ route('pos') }}" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm shadow-indigo-600/20 hover:bg-blue-700">
                    @include('partials.icon', ['name' => 'shopping-cart', 'class' => 'h-4 w-4'])
                    Sell
                </a>
            @endif
        </div>
    </div>
</header>
