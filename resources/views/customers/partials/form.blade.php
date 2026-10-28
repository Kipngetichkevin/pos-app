@php
    $c = $editing;
    $isManager = auth()->user()->isManager();
@endphp
<form method="POST" action="{{ $c ? route('customers.update', $c) : route('customers.store') }}" class="grid grid-cols-1 gap-3 md:grid-cols-2">
    @csrf
    @if ($c) @method('PUT') @endif

    <div class="md:col-span-2">
        <label class="mb-1 block text-sm font-medium">Name *</label>
        <input name="name" value="{{ old('name', $c?->name) }}" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        @include('partials.error', ['field' => 'name'])
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium">Phone</label>
        <input name="phone" value="{{ old('phone', $c?->phone) }}" type="tel" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        @include('partials.error', ['field' => 'phone'])
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium">Email</label>
        <input name="email" value="{{ old('email', $c?->email) }}" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        @include('partials.error', ['field' => 'email'])
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium">KRA PIN</label>
        <input name="kra_pin" value="{{ old('kra_pin', $c?->kra_pin) }}" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 uppercase">
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium">Credit limit (KSh) {{ $isManager ? '' : '- manager only' }}</label>
        <input name="credit_limit" value="{{ old('credit_limit', $c ? (float) $c->credit_limit : 0) }}" type="number" step="0.01" min="0" @disabled(! $isManager)
               class="w-full rounded-lg border border-slate-300 px-3 py-2 disabled:bg-slate-100">
        <p class="mt-1 text-xs text-slate-500">0 = no credit allowed</p>
        @include('partials.error', ['field' => 'credit_limit'])
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium">Prices {{ $isManager ? '' : '- manager only' }}</label>
        <select name="price_level" @disabled(! $isManager) class="w-full rounded-lg border border-slate-300 px-3 py-2 disabled:bg-slate-100">
            <option value="retail" @selected(old('price_level', $c?->price_level ?? 'retail') === 'retail')>Retail (normal prices)</option>
            <option value="wholesale" @selected(old('price_level', $c?->price_level) === 'wholesale')>Wholesale (always wholesale prices)</option>
        </select>
        @include('partials.error', ['field' => 'price_level'])
    </div>
    <label class="flex items-center gap-2 text-sm md:col-span-2">
        <input type="hidden" name="is_active" value="0">
        <input name="is_active" value="1" type="checkbox" @checked(old('is_active', $c?->is_active ?? true)) class="rounded border-slate-300"> Active
    </label>
    <div class="flex justify-end gap-2 md:col-span-2">
        <a href="{{ $listUrl }}" class="rounded-lg border border-slate-300 px-4 py-2 hover:bg-slate-50">Cancel</a>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">Save</button>
    </div>
</form>
