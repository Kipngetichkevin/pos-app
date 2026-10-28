<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockLevel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackagingController extends Controller
{
    public function index(Request $request, Product $product)
    {
        $level = StockLevel::where('branch_id', $request->user()->branch_id)->where('product_id', $product->id)->first();

        return view('products.packaging', [
            'product' => $product,
            'packagings' => $product->packagings()->orderByDesc('is_active')->orderBy('quantity')->get(),
            'base' => $product->unit?->short_name ?? 'pc',
            'avgCost' => (float) ($level?->average_cost ?: $product->cost_price),
            'stock' => (float) ($level?->quantity ?? 0),
        ]);
    }

    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'quantity' => 'required|numeric|gt:1',
            'price' => 'nullable|numeric|min:0',
            'barcode' => ['nullable', 'string', 'max:50', Rule::unique('product_units', 'barcode'), Rule::unique('product_barcodes', 'barcode')],
        ], [
            'name.required' => 'Name it, e.g. Carton, Packet, Box, Dozen.',
            'quantity.gt' => 'A packaging must hold more than 1.',
            'barcode.unique' => 'This barcode is already used.',
        ]);

        $price = $data['price'] ?? null;

        ProductUnit::create([
            'product_id' => $product->id,
            'name' => trim($data['name']),
            'quantity' => (float) $data['quantity'],
            'selling_price' => $price === '' || $price === null ? null : round((float) $price, 2),
            'barcode' => trim((string) ($data['barcode'] ?? '')) ?: null,
        ]);

        return redirect()->route('products.packaging', $product)->with('status', 'Added ' . trim($data['name']) . '.');
    }

    public function update(Request $request, Product $product, ProductUnit $unit)
    {
        $this->ensureBelongs($product, $unit);

        $price = $request->input('price');

        if ($price === '' || $price === null) {
            $unit->update(['selling_price' => null]);

            return back()->with('status', "{$unit->name} is now for buying only (not sold as a whole).");
        }

        if (is_numeric($price) && (float) $price >= 0) {
            $unit->update(['selling_price' => round((float) $price, 2)]);

            return back()->with('status', "{$unit->name} price updated.");
        }

        return back();
    }

    public function makeDefault(Product $product, ProductUnit $unit)
    {
        $this->ensureBelongs($product, $unit);

        $product->packagings()->update(['is_default_purchase' => false]);
        $product->packagings()->whereKey($unit->id)->update(['is_default_purchase' => true]);

        return back()->with('status', 'Default buying unit updated.');
    }

    public function toggle(Product $product, ProductUnit $unit)
    {
        $this->ensureBelongs($product, $unit);

        $unit->update(['is_active' => ! $unit->is_active]);

        return back();
    }

    protected function ensureBelongs(Product $product, ProductUnit $unit): void
    {
        abort_unless($unit->product_id === $product->id, 404);
    }
}
