<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $branchId = $request->user()->branch_id;

        $products = Product::query()
            ->with([
                'unit',
                'category',
                'barcodes',
                'stockLevels' => fn ($q) => $q->where('branch_id', $branchId),
            ])
            ->when($search, function ($query) use ($search) {
                $term = '%' . $search . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('sku', 'like', $term)
                        ->orWhereHas('barcodes', fn ($b) => $b->where('barcode', 'like', $term));
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // ?create=1 opens the add form, ?edit=ID opens the edit form
        $editing = $request->query('edit') ? Product::with('barcodes')->findOrFail($request->query('edit')) : null;
        $showForm = $request->boolean('create') || $editing;

        return view('products.index', [
            'products' => $products,
            'search' => $search,
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
            'taxRates' => TaxRate::where('is_active', true)->orderBy('code')->get(),
            'showForm' => $showForm,
            'editing' => $editing,
            // Defaults for a new product: pieces, tax code B, General category
            'defaults' => $showForm && ! $editing ? [
                'unit_id' => Unit::where('short_name', 'pc')->value('id'),
                'tax_rate_id' => TaxRate::where('code', 'B')->value('id'),
                'category_id' => Category::where('name', 'General')->value('id'),
            ] : [],
            'listQuery' => $request->except(['create', 'edit']),
        ]);
    }

    public function store(Request $request, StockService $stock)
    {
        $data = $this->validated($request);

        $this->save($request, $data, new Product(), $stock);

        return $this->backToList()->with('status', 'Product added.');
    }

    public function update(Request $request, Product $product, StockService $stock)
    {
        $data = $this->validated($request, $product);

        $this->save($request, $data, $product, $stock);

        return $this->backToList()->with('status', 'Product updated.');
    }

    public function toggle(Product $product)
    {
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('status', $product->name . ($product->is_active ? ' activated.' : ' deactivated.'));
    }

    protected function validated(Request $request, ?Product $product = null): array
    {
        $editingId = $product?->id;

        return $request->validate([
            'name' => 'required|string|max:150',
            'sku' => ['nullable', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($editingId)],
            'barcode' => [
                'nullable', 'string', 'max:50',
                Rule::unique('product_barcodes', 'barcode')->where(
                    fn ($q) => $editingId ? $q->where('product_id', '!=', $editingId) : $q
                ),
            ],
            'category_id' => 'nullable|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'tax_rate_id' => 'required|exists:tax_rates,id',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0|lte:selling_price',
            'wholesale_min_qty' => 'nullable|numeric|gt:0',
            'etims_class_code' => 'nullable|digits_between:8,10',
            'reorder_level' => 'nullable|numeric|min:0',
            'opening_stock' => 'nullable|numeric|min:0',
            'packName' => 'nullable|string|max:50',
            'packQty' => 'nullable|numeric|gt:1',
            'packPrice' => 'nullable|numeric|min:0',
        ], [
            'barcode.unique' => 'This barcode is already used by another product.',
            'sku.unique' => 'This SKU is already used by another product.',
            'wholesale_price.lte' => 'The wholesale price should not be more than the retail price.',
            'etims_class_code.digits_between' => 'KRA classification codes are 8 to 10 digits, e.g. 5010150000.',
        ]);
    }

    protected function save(Request $request, array $data, Product $product, StockService $stock): void
    {
        $user = $request->user();
        $isNew = ! $product->exists;
        $trackStock = $request->boolean('track_stock');

        DB::transaction(function () use ($request, $data, $product, $stock, $user, $isNew, $trackStock) {
            $product->fill([
                'name' => trim($data['name']),
                'sku' => trim((string) ($data['sku'] ?? '')) ?: ($product->sku ?? 'P-' . strtoupper(Str::random(8))),
                'category_id' => ($data['category_id'] ?? null) ?: null,
                'unit_id' => $data['unit_id'],
                'tax_rate_id' => $data['tax_rate_id'],
                'cost_price' => (float) $data['cost_price'],
                'selling_price' => (float) $data['selling_price'],
                'wholesale_price' => ($data['wholesale_price'] ?? null) === null ? null : round((float) $data['wholesale_price'], 2),
                'wholesale_min_qty' => ($data['wholesale_price'] ?? null) === null || ($data['wholesale_min_qty'] ?? null) === null ? null : (float) $data['wholesale_min_qty'],
                'reorder_level' => (float) (($data['reorder_level'] ?? 0) ?: 0),
                'etims_class_code' => ($data['etims_class_code'] ?? null) ?: null,
                'is_weighed' => $request->boolean('is_weighed'),
                'track_stock' => $trackStock,
            ])->save();

            // Main barcode: create, update or remove
            $existing = $product->barcodes()->oldest()->first();
            $barcode = trim((string) ($data['barcode'] ?? ''));

            if ($barcode !== '') {
                $existing
                    ? $existing->update(['barcode' => $barcode])
                    : $product->barcodes()->create(['barcode' => $barcode, 'pack_quantity' => 1]);
            } elseif ($existing) {
                $existing->delete();
            }

            // Packaging entered on the add form (e.g. Carton = 12)
            $packQty = (float) ($data['packQty'] ?? 0);

            if ($isNew && $packQty > 1) {
                $packPrice = $data['packPrice'] ?? null;

                ProductUnit::create([
                    'product_id' => $product->id,
                    'name' => trim((string) ($data['packName'] ?? '')) ?: 'Carton',
                    'quantity' => $packQty,
                    'selling_price' => $packPrice === '' || $packPrice === null ? null : round((float) $packPrice, 2),
                    'is_default_purchase' => true,
                ]);
            }

            // Opening stock (new products only)
            $opening = (float) ($data['opening_stock'] ?? 0);

            if ($isNew && $trackStock && $opening > 0) {
                $stock->move(
                    product: $product,
                    branchId: $user->branch_id,
                    quantity: $opening,
                    type: 'opening',
                    userId: $user->id,
                    unitCost: (float) $data['cost_price'],
                    reason: 'Opening stock',
                );
            }
        });
    }

    /**
     * Back to the list the form was opened from, keeping search and page but closing the modal.
     */
    protected function backToList(): RedirectResponse
    {
        parse_str((string) parse_url(url()->previous(), PHP_URL_QUERY), $query);
        unset($query['create'], $query['edit']);

        return redirect()->route('products.index', $query);
    }
}
