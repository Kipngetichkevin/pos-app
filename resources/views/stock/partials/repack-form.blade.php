@php
    $activeList = $packs->where('is_active', true);
    $packData = $activeList->map(fn ($p) => ['id' => $p->id, 'size' => (float) $p->pack_size])->values();
    $initial = [
        'counts' => (object) collect((array) old('counts', []))->filter(fn ($c) => is_scalar($c))->all(),
        'bulkUsed' => (string) old('bulkUsed', ''),
    ];
@endphp
<div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
    <h3 class="mb-1 font-semibold">Repack from the bulk</h3>
    <p class="mb-3 text-sm text-slate-500">Enter how many packs you made. Stock moves from the bulk to the packs.</p>

    @if ($activeList->isEmpty())
        <p class="text-sm text-slate-500">Add pack sizes first.</p>
    @else
        <form method="POST" action="{{ route('stock.packs.repack', $product) }}"
              x-data="repackForm(@js($packData), @js($bulk['qty']), @js((string) $unit), @js($initial))"
              onsubmit="return confirm('Save this repack?')">
            @csrf
            <div class="space-y-2">
                @foreach ($activeList as $pack)
                    <div class="flex items-center gap-2 text-sm">
                        <span class="flex-1">{{ $pack->name }}</span>
                        <input name="counts[{{ $pack->id }}]" x-model="counts[@js($pack->id)]" type="number" min="0" step="1" placeholder="0"
                               class="w-24 rounded border border-slate-300 px-2 py-1 text-center">
                        <span class="w-24 text-right text-slate-500" x-text="'= ' + fq(lineQty(@js($pack->id))) + ' ' + unit"></span>
                    </div>
                @endforeach
            </div>
            @error('counts') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="mt-4">
                <label class="mb-1 block text-sm font-medium">Actually taken from the bulk ({{ $unit }})</label>
                <input name="bulkUsed" x-model="bulkUsed" type="number" step="0.001" min="0"
                       x-bind:placeholder="fq(packed) + ' (same as packed)'"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2">
                <p class="mt-1 text-xs text-slate-500">Weigh what's left in the bag if you want to record spillage or overfilling.</p>
                @error('bulkUsed') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="mt-4 space-y-1 rounded-lg bg-slate-50 p-3 text-sm">
                <div class="flex justify-between"><span>Packed</span><span x-text="fq(packed) + ' ' + unit"></span></div>
                <div class="flex justify-between"><span>Taken from bulk</span><span x-text="fq(used) + ' ' + unit"></span></div>
                <template x-if="used - packed > 0.0005">
                    <div class="flex justify-between font-semibold text-amber-700"><span>Repacking loss</span><span x-text="fq(used - packed) + ' ' + unit"></span></div>
                </template>
                <div class="flex justify-between"><span>Bulk left after</span><span x-text="fq(bulkQty - used) + ' ' + unit"></span></div>
            </div>

            <button type="submit" class="mt-3 w-full rounded-xl bg-green-600 py-3 font-bold text-white hover:bg-green-700">Save repack</button>
        </form>
    @endif
</div>
