<form method="POST" action="{{ route('stock-takes.store') }}" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    @csrf
    <div>
        <h3 class="font-semibold">Start a stock take</h3>
        <p class="text-sm text-slate-500">You can keep selling while you count. Each item's expected quantity is taken at the moment you count it.</p>
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium">What to count</label>
        <select name="category_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            <option value="">Whole shop (all products)</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->id }}" @selected(old('category_id') === $cat->id)>Only {{ $cat->name }}</option>
            @endforeach
        </select>
        @include('partials.error', ['field' => 'category_id'])
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium">Notes</label>
        <input name="notes" value="{{ old('notes') }}" type="text" placeholder="e.g. Month-end count" class="w-full rounded-lg border border-slate-300 px-3 py-2">
    </div>
    <button class="w-full rounded-lg bg-blue-600 py-2.5 font-semibold text-white hover:bg-blue-700">Start counting</button>
</form>
