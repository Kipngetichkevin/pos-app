@php
    $tabs = ['failed' => 'Failed', 'queued' => 'Waiting', 'sent' => 'Accepted by KRA', 'not_required' => 'Not sent', 'all' => 'All'];
@endphp
<div class="mb-3 flex flex-wrap gap-2">
    @foreach ($tabs as $key => $label)
        <a href="{{ route('etims.index', ['status' => $key]) }}"
           class="rounded-full px-3 py-1 text-sm font-medium {{ $status === $key ? 'bg-blue-600 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50' }}">
            {{ $label }}
            @if ($key !== 'all') <b>{{ $counts[$key] ?? 0 }}</b> @endif
        </a>
    @endforeach
</div>
