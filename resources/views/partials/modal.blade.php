{{--
    Server-rendered modal. Shown when the page is opened with a query flag (e.g. ?edit=ID).
    Usage: @include('partials.modal', ['title' => '...', 'close' => url, 'body' => 'module.partials.form', 'width' => 'max-w-lg'])
    Pass any extra data the body partial needs in the same array.
--}}
<div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4">
    <div class="mt-10 w-full {{ $width ?? 'max-w-lg' }} rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-bold">{{ $title }}</h3>
            <a href="{{ $close }}" class="text-2xl leading-none text-slate-400 hover:text-slate-600">&times;</a>
        </div>
        @include($body)
    </div>
</div>
