@php
    $user = auth()->user();
    $manager = $user->isManager();
    $route = fn ($name) => \Illuminate\Support\Facades\Route::has($name) ? route($name) : null;

    // [route, label, icon, active-pattern, visible]
    $groups = [
        '' => [
            ['dashboard', 'Dashboard', 'home', 'dashboard', true],
            ['pos', 'Sell', 'shopping-cart', 'pos*', true],
            ['returns', 'Returns', 'arrow-uturn-left', 'returns*', true],
            ['customers.index', 'Customers', 'users', 'customers*', true],
            ['expenses.index', 'Expenses', 'banknotes', 'expenses*', true],
        ],
        'Stock' => [
            ['products.index', 'Products', 'tag', 'products*', $manager],
            ['stock.index', 'Stock levels', 'cube', 'stock.index', $manager],
            ['stock.receive', 'Receive delivery', 'truck', 'stock.receive*', $manager],
            ['transfers.index', 'Transfers', 'arrows-right-left', 'transfers*', $manager],
            ['stock-takes.index', 'Stock take', 'clipboard-document-check', 'stock-takes*', $manager],
        ],
        'Business' => [
            ['reports.index', 'Reports', 'presentation-chart-line', 'reports*', $manager],
            ['sales.index', 'Sales & M-Pesa', 'chart-bar', 'sales*', $manager],
            ['shifts.index', 'Shifts', 'clock', 'shifts*', $manager],
        ],
        'Admin' => [
            ['users.index', 'Users', 'user-group', 'users*', $manager],
            ['audit.index', 'Audit log', 'shield-check', 'audit*', $manager],
            ['etims.index', 'KRA eTIMS', 'document-check', 'etims*', $manager],
        ],
    ];
@endphp

{{-- Mobile backdrop --}}
<div x-show="nav" x-cloak x-on:click="nav = false" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/50 lg:hidden"></div>

<aside :class="nav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-shell text-slate-300 transition-transform duration-200 lg:translate-x-0">
    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-5 py-5">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-500 text-white shadow-lg shadow-indigo-900/40">
            @include('partials.icon', ['name' => 'building-storefront', 'class' => 'h-6 w-6'])
        </span>
        <span class="min-w-0">
            <span class="block truncate text-base font-bold text-white">{{ config('app.name') }}</span>
            <span class="block truncate text-xs text-slate-400">{{ $user->branch?->name }}</span>
        </span>
    </a>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 pb-6">
        @foreach ($groups as $title => $links)
            @php $links = array_filter($links, fn ($l) => $l[4] && $route($l[0])); @endphp
            @if ($links)
                <div>
                    @if ($title)
                        <div class="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $title }}</div>
                    @endif
                    @foreach ($links as [$name, $label, $icon, $pattern])
                        @php $active = request()->routeIs($pattern); @endphp
                        <a href="{{ $route($name) }}"
                           class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium
                                  {{ $active ? 'bg-indigo-500/15 text-white ring-1 ring-inset ring-indigo-400/30' : 'hover:bg-shell-hover hover:text-white' }}">
                            @include('partials.icon', ['name' => $icon, 'class' => 'h-5 w-5 shrink-0 ' . ($active ? 'text-indigo-300' : 'text-slate-500 group-hover:text-slate-300')])
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            @endif
        @endforeach
    </nav>

    <div class="border-t border-white/5 p-3">
        <div class="flex items-center gap-3 rounded-lg px-2 py-2">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-700 text-sm font-semibold text-white">
                {{ collect(explode(' ', $user->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('') }}
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-medium text-white">{{ $user->name }}</span>
                <span class="block text-xs capitalize text-slate-400">{{ $user->role }}</span>
            </span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button title="Log out" class="rounded-lg p-2 text-slate-400 hover:bg-shell-hover hover:text-white">
                    @include('partials.icon', ['name' => 'arrow-right-start-on-rectangle', 'class' => 'h-5 w-5'])
                </button>
            </form>
        </div>
    </div>
</aside>
