@php
    $unitName = $adjusting->unit?->short_name;
    $initial = [
        'mode' => old('adjustMode', 'remove'),
        'qty' => (string) old('adjustQty', ''),
        'reason' => old('adjustReason', 'damaged'),
    ];
@endphp
<div x-data="stockAdjust(@js($adjustCurrent),@js($initial))">
    <p class="-mt-3 mb-4 text-sm text-slate-500">{{ $adjusting->name }} &middot; currently <b>{{ $fmtQty($adjustCurrent) }} {{ $unitName }}</b></p>

    <form method="POST" action="{{ route('stock.adjust', $adjusting) }}" class="space-y-3">
        @csrf
        <input type="hidden" name="adjustMode" value="{{ $initial['mode'] }}" x-bind:value="mode">

        <div class="grid grid-cols-3 gap-1 rounded-lg bg-slate-100 p-1 text-sm">
            @foreach (['remove' => 'Remove', 'add' => 'Add', 'set' => 'Set counted'] as $mode => $label)
                <button type="button" x-on:click="mode = '{{ $mode }}'"
                        class="rounded-md py-1.5 font-semibold"
                        x-bind:class="mode === '{{ $mode }}' ? 'bg-white shadow-sm' : 'text-slate-500'">{{ $label }}</button>
            @endforeach
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium" x-text="mode === 'set' ? 'Quantity counted on the shelf' : 'Quantity'">Quantity</label>
            <input name="adjustQty" x-model="qty" type="number" step="0.001" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            @include('partials.error', ['field' => 'adjustQty'])
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium">Reason</label>
            <select name="adjustReason" x-model="reason" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @foreach ($reasons as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <input name="adjustNote" value="{{ old('adjustNote') }}" type="text"
                   x-bind:placeholder="'Note ' + (reason === 'other' ? '(required)' : '(optional)')"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2">
            @include('partials.error', ['field' => 'adjustNote'])
        </div>

        <template x-if="newStock !== null">
            <div class="rounded-lg bg-slate-50 p-3 text-sm">
                New stock will be <b x-bind:class="newStock < 0 ? 'text-red-600' : ''" x-text="fmt(newStock) + ' ' + unit"></b>
            </div>
        </template>

        <div class="flex justify-end gap-2">
            <a href="{{ $listUrl }}" class="rounded-lg border border-slate-300 px-4 py-2 hover:bg-slate-50">Cancel</a>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">Save adjustment</button>
        </div>
    </form>
</div>
