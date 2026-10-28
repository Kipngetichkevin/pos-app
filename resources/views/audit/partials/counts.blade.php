@if ($counts->isNotEmpty())
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($counts as $key => $n)
            <a href="{{ request()->fullUrlWithQuery(['action' => $key, 'page' => null]) }}"
               class="rounded-full px-3 py-1 text-sm {{ $action === $key ? 'bg-blue-600 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                {{ \App\Models\AuditLog::ACTIONS[$key] ?? $key }} <b>{{ $n }}</b>
            </a>
        @endforeach
    </div>
@endif
