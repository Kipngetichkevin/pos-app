@php
    $labels = ['off' => 'Off', 'fake' => 'Training (fake KRA)', 'oscu' => 'KRA OSCU (direct)', 'integrator' => 'eTIMS integrator'];
@endphp
<div class="mb-4 grid gap-4 md:grid-cols-3">
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div class="text-xs uppercase text-slate-500">Connection</div>
        <div class="mt-1 text-lg font-bold {{ $driver === 'fake' ? 'text-amber-600' : ($enabled ? 'text-green-700' : 'text-slate-500') }}">{{ $labels[$driver] ?? $driver }}</div>
        @if ($driver === 'fake')
            <p class="mt-1 text-xs text-amber-700">Invoices are NOT going to KRA. Use only for training.</p>
        @elseif (! $enabled)
            <p class="mt-1 text-xs text-slate-500">Set ETIMS_DRIVER in .env to switch it on.</p>
        @endif
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div class="text-xs uppercase text-slate-500">KRA PIN &middot; eTIMS branch</div>
        <div class="mt-1 font-mono text-lg font-bold">{{ $pin ?: 'not set' }} &middot; {{ $bhfId }}</div>
    </div>
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div class="text-xs uppercase text-slate-500">Products without KRA classification</div>
        <div class="mt-1 text-lg font-bold {{ $unclassified ? 'text-amber-600' : 'text-green-700' }}">{{ $unclassified }}</div>
        @if ($unclassified && $driver !== 'fake')
            <p class="mt-1 text-xs text-slate-500">KRA rejects invoices with unclassified items. Set them on the Products screen.</p>
        @endif
    </div>
</div>
