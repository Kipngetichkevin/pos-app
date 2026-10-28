@php
    $p = $editing;
    $unitMap = $units->mapWithKeys(fn ($u) => [$u->id => ['short' => $u->short_name, 'decimal' => (bool) $u->allows_decimal]]);
    $unitId = (string) old('unit_id', $p?->unit_id ?? ($defaults['unit_id'] ?? ''));
    $categoryId = (string) old('category_id', $p ? $p->category_id : ($defaults['category_id'] ?? ''));
    $taxRateId = (string) old('tax_rate_id', $p?->tax_rate_id ?? ($defaults['tax_rate_id'] ?? ''));
@endphp
<form method="POST" action="{{ $p ? route('products.update', $p) : route('products.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2"
      x-data="{
          units: @js($unitMap),
          unitId: @js($unitId),
          containerSize: @js((string) old('containerSize', '')),
          containerCost: @js((string) old('containerCost', '')),
          cost: @js((string) old('cost_price', $p?->cost_price ?? '')),
          busy: false,
          get unit() { return this.units[this.unitId] ?? null },
          get short() { return this.unit?.short ?? 'unit' },
          get hasContainer() { return Number(this.containerSize) > 0 && Number(this.containerCost) > 0 },
          applyContainer() {
              // Bulk calculator: cost per base unit = container cost / container size
              if (this.hasContainer) {
                  this.cost = String(Math.round(Number(this.containerCost) / Number(this.containerSize) * 100) / 100);
              }
          },
      }"
      x-init="$watch('containerSize', () => applyContainer()); $watch('containerCost', () => applyContainer())"
      x-on:submit="busy = true">
    @csrf
    @if ($p) @method('PUT') @endif

    <div class="md:col-span-2">
        <label class="mb-1 block text-sm font-medium">Product name *</label>
        <input name="name" value="{{ old('name', $p?->name) }}" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        @include('partials.error', ['field' => 'name'])
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Barcode</label>
        <input name="barcode" value="{{ old('barcode', $p?->barcodes->first()?->barcode) }}" x-on:keydown.enter.prevent type="text" placeholder="Scan or type"
               class="w-full rounded-lg border border-slate-300 px-3 py-2">
        @include('partials.error', ['field' => 'barcode'])
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">SKU</label>
        <input name="sku" value="{{ old('sku', $p?->sku) }}" type="text" placeholder="Leave blank to auto-generate"
               class="w-full rounded-lg border border-slate-300 px-3 py-2">
        @include('partials.error', ['field' => 'sku'])
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Category</label>
        <select name="category_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            <option value="">-- None --</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected($categoryId === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Unit *</label>
        <select name="unit_id" x-model="unitId" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            @foreach ($units as $unit)
                <option value="{{ $unit->id }}" @selected($unitId === (string) $unit->id)>{{ $unit->name }} ({{ $unit->short_name }})</option>
            @endforeach
        </select>
        @include('partials.error', ['field' => 'unit_id'])
    </div>

    <div x-show="unit?.decimal" x-cloak class="rounded-lg bg-blue-50 p-3 md:col-span-2">
        <div class="mb-2 text-sm font-semibold text-blue-900">Bought in a bag or jerrycan? Work out the cost per <span x-text="short"></span></div>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span>It holds</span>
            <input name="containerSize" x-model.debounce.400ms="containerSize" type="number" step="0.001" min="0" placeholder="e.g. 20"
                   class="w-24 rounded border border-slate-300 px-2 py-1">
            <span><span x-text="short"></span> and cost KSh</span>
            <input name="containerCost" x-model.debounce.400ms="containerCost" type="number" step="0.01" min="0" placeholder="e.g. 6000"
                   class="w-28 rounded border border-slate-300 px-2 py-1">
            <template x-if="hasContainer">
                <span class="font-semibold text-blue-900">= <span x-text="money(Number(containerCost) / Number(containerSize))"></span> per <span x-text="short"></span></span>
            </template>
        </div>
        <p class="mt-2 text-xs text-blue-800">Selling price below is also per <span x-text="short"></span> (for selling loose). If you only sell in packs, enter 0 and set pack prices on Stock &rarr; Packs.</p>
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Buying price per <span x-text="short">{{ $unitMap[$unitId]['short'] ?? 'unit' }}</span> (KSh) *</label>
        <input name="cost_price" x-model="cost" type="number" step="0.01" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        @include('partials.error', ['field' => 'cost_price'])
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Selling price per <span x-text="short">{{ $unitMap[$unitId]['short'] ?? 'unit' }}</span> (KSh, incl. VAT) *</label>
        <input name="selling_price" value="{{ old('selling_price', $p?->selling_price) }}" type="number" step="0.01" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        @include('partials.error', ['field' => 'selling_price'])
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Wholesale price per <span x-text="short">{{ $unitMap[$unitId]['short'] ?? 'unit' }}</span> (optional)</label>
        <input name="wholesale_price" value="{{ old('wholesale_price', $p?->wholesale_price) }}" type="number" step="0.01" min="0" placeholder="Blank = no wholesale" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        @include('partials.error', ['field' => 'wholesale_price'])
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Wholesale from quantity</label>
        <input name="wholesale_min_qty" value="{{ old('wholesale_min_qty', $p && $p->wholesale_min_qty !== null ? (float) $p->wholesale_min_qty : '') }}" type="number" step="0.001" min="0" placeholder="Blank = wholesale customers only" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        <p class="mt-1 text-xs text-slate-500">e.g. 6: anyone buying 6 or more pays the wholesale price.</p>
        @include('partials.error', ['field' => 'wholesale_min_qty'])
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Tax *</label>
        <select name="tax_rate_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            @foreach ($taxRates as $tax)
                <option value="{{ $tax->id }}" @selected($taxRateId === (string) $tax->id)>{{ $tax->code }} - {{ $tax->name }}</option>
            @endforeach
        </select>
        @include('partials.error', ['field' => 'tax_rate_id'])
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">KRA eTIMS item classification</label>
        <input name="etims_class_code" value="{{ old('etims_class_code', $p?->etims_class_code) }}" type="text" inputmode="numeric" placeholder="e.g. 5010150000" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono">
        <p class="mt-1 text-xs text-slate-500">From KRA's item classification list. Needed before KRA accepts invoices with this item.</p>
        @include('partials.error', ['field' => 'etims_class_code'])
    </div>

    <div>
        <label class="mb-1 block text-sm font-medium">Reorder level</label>
        <input name="reorder_level" value="{{ old('reorder_level', $p ? (float) $p->reorder_level : 0) }}" type="number" step="0.001" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        @include('partials.error', ['field' => 'reorder_level'])
    </div>

    @unless ($p)
        <div class="md:col-span-2">
            <label class="mb-1 block text-sm font-medium">Opening stock (quantity on hand now)</label>
            <input name="opening_stock" value="{{ old('opening_stock') }}" type="number" step="0.001" min="0" placeholder="0"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2">
            @include('partials.error', ['field' => 'opening_stock'])
        </div>
        <div class="rounded-lg bg-green-50 p-3 md:col-span-2">
            <div class="mb-2 text-sm font-semibold text-green-900">Comes in a carton or pack? (optional)</div>
            <div class="grid grid-cols-3 gap-2">
                <input name="packName" value="{{ old('packName', 'Carton') }}" type="text" placeholder="Carton" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
                <input name="packQty" value="{{ old('packQty') }}" type="number" step="1" min="0" placeholder="Holds how many" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
                <input name="packPrice" value="{{ old('packPrice') }}" type="number" step="0.01" min="0" placeholder="Carton price (blank = not sold whole)" class="rounded border border-slate-300 px-2 py-1.5 text-sm">
            </div>
            @error('packQty') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <p class="mt-2 text-xs text-green-800">Example: Minute Maid, Carton holds 12, carton price 800. The prices above are for ONE bottle/piece. Opening stock is also counted in single units.</p>
        </div>
    @endunless

    <div class="flex flex-wrap gap-6 md:col-span-2">
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="track_stock" value="0">
            <input name="track_stock" value="1" type="checkbox" @checked(old('track_stock', $p?->track_stock ?? true)) class="rounded border-slate-300">
            Track stock
        </label>
        <label class="flex items-center gap-2 text-sm">
            <input type="hidden" name="is_weighed" value="0">
            <input name="is_weighed" value="1" type="checkbox" @checked(old('is_weighed', $p?->is_weighed ?? false)) class="rounded border-slate-300">
            Sold by weight (scale)
        </label>
    </div>

    <div class="flex justify-end gap-2 md:col-span-2">
        <a href="{{ route('products.index', $listQuery) }}" class="rounded-lg border border-slate-300 px-4 py-2 hover:bg-slate-50">
            Cancel
        </a>
        <button type="submit" x-bind:disabled="busy" class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
            <span x-show="!busy">Save product</span>
            <span x-show="busy" x-cloak>Saving...</span>
        </button>
    </div>
</form>
