{{-- Branch servers only: shows whether sales are reaching the cloud --}}
@php $sync = \App\Sync\SyncStatus::summary(); @endphp
@if ($sync)
    @php
        $title = $sync['last_success'] ? 'Last synced ' . $sync['last_success']->diffForHumans() : 'Never synced';
        if ($sync['state'] !== 'ok' && $sync['last_error']) {
            $title .= ' - ' . $sync['last_error'];
        }
    @endphp
    <span title="{{ $title }}"
          class="hidden items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold sm:inline-flex
                 {{ $sync['state'] === 'ok' ? 'bg-green-50 text-green-700' : ($sync['state'] === 'off' ? 'bg-slate-100 text-slate-600' : 'bg-amber-50 text-amber-800') }}">
        <span class="h-2 w-2 rounded-full {{ $sync['state'] === 'ok' ? 'bg-green-500' : ($sync['state'] === 'off' ? 'bg-slate-400' : 'bg-amber-500') }}"></span>
        @if ($sync['state'] === 'ok')
            Online{{ $sync['pending_sales'] ? ' · ' . $sync['pending_sales'] . ' to send' : '' }}
        @elseif ($sync['state'] === 'off')
            Sync not set up
        @else
            Offline{{ $sync['pending_sales'] ? ' · ' . $sync['pending_sales'] . ' sales waiting' : '' }}
        @endif
    </span>
@endif
