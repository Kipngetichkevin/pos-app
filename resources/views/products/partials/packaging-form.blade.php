<form method="POST" action="{{ route('products.packaging.store', $product) }}" class="mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
    @csrf
    <div class="mb-3 font-semibold">Add packaging</div>
    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
        <div>
            <input name="name" value="{{ old('name') }}" type="text" placeholder="Name: Carton, Packet, Box" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            @error('name') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <input name="quantity" value="{{ old('quantity') }}" type="number" step="0.001" min="0" placeholder="Holds how many {{ $base }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            @error('quantity') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <input name="price" value="{{ old('price') }}" type="number" step="0.01" min="0" placeholder="Price (blank = not sold whole)" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            @error('price') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <input name="barcode" value="{{ old('barcode') }}" type="text" placeholder="Barcode (optional)" class="w-full rounded-lg border border-slate-300 px-3 py-2">
            @error('barcode') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>
    <p class="mt-2 text-xs text-slate-500">Always count in {{ $base }}. A box of 20 packets of 10 pieces holds 200, not 20.</p>
    <button type="submit" class="mt-3 rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">Add packaging</button>
</form>
