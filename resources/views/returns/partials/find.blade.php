<form method="GET" action="{{ route('returns') }}" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
    <label class="mb-1 block text-sm font-medium">Receipt number</label>
    <div class="flex gap-2">
        <input name="receipt" value="{{ old('receipt') }}" type="text" placeholder="e.g. MAIN-T01-000012" autofocus
               class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 uppercase">
        <button class="rounded-lg bg-blue-600 px-4 font-semibold text-white hover:bg-blue-700">Find</button>
    </div>
    @include('partials.error', ['field' => 'receiptNo'])
</form>
