@if (session('status'))
    <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800 ring-1 ring-green-200">
        {{ session('status') }}
        @if (session('status_link'))
            <a href="{{ session('status_link')['url'] }}" target="_blank" class="ml-2 font-semibold underline">{{ session('status_link')['label'] }}</a>
        @endif
    </div>
@endif
@if (session('warning'))
    <div class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">{{ session('warning') }}</div>
@endif
@error('general')
    <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">{{ $message }}</div>
@enderror
