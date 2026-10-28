<div class="mx-auto max-w-md rounded-3xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
        @include('partials.icon', ['name' => 'banknotes', 'class' => 'h-7 w-7'])
    </span>
    <h2 class="mt-4 text-xl font-bold text-slate-900">Open a shift</h2>
    <p class="mb-6 mt-1 text-sm text-slate-500">Count the cash in the drawer before you start selling. You will count it again when you close.</p>
    <form method="POST" action="{{ route('pos.shift') }}" class="space-y-4">
        @csrf
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Till</label>
            <select name="till_id" class="w-full rounded-xl border border-slate-200 px-4 py-3">
                @foreach ($tills as $till)
                    <option value="{{ $till->id }}" @selected(old('till_id') === $till->id)>{{ $till->code }} - {{ $till->name }}</option>
                @endforeach
            </select>
            @include('partials.error', ['field' => 'till_id'])
        </div>
        <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Opening cash in drawer (KSh)</label>
            <input name="opening_float" value="{{ old('opening_float') }}" type="number" step="0.01" min="0" placeholder="0.00" autofocus
                   class="tabular w-full rounded-xl border border-slate-200 px-4 py-3 text-xl font-semibold">
            @include('partials.error', ['field' => 'opening_float'])
        </div>
        <button type="submit" class="w-full rounded-xl bg-indigo-600 py-3.5 font-semibold text-white shadow-lg shadow-indigo-600/25 hover:bg-indigo-700">Open shift &amp; start selling</button>
    </form>
</div>
