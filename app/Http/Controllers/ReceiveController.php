<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use App\Models\Supplier;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceiveController extends Controller
{
    public function create()
    {
        return view('stock.receive', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'recent' => GoodsReceipt::with(['supplier', 'user'])->withCount('items')->latest()->limit(10)->get(),
        ]);
    }

    /**
     * Scan / search for a product to add to the delivery.
     * ?q=term scans like the old addScan; ?product=ID adds a product picked from the search results.
     * Returns {line: {...}} or {results: [...]}; errors come back as 422 on "scan".
     */
    public function lookup(Request $request)
    {
        if ($request->filled('product')) {
            return $this->line((string) $request->query('product'));
        }

        $term = trim((string) $request->query('q'));

        if ($term === '') {
            return response()->json(['results' => []]);
        }

        $packaging = ProductUnit::where('barcode', $term)->where('is_active', true)->first();
        if ($packaging) {
            return $this->line($packaging->product_id, 1, $packaging->id);
        }

        $barcode = ProductBarcode::where('barcode', $term)->first();
        if ($barcode) {
            return $this->line($barcode->product_id, (float) $barcode->pack_quantity);
        }

        $bySku = Product::where('sku', $term)->first();
        if ($bySku) {
            return $this->line($bySku->id);
        }

        $matches = Product::where('track_stock', true)->where('name', 'like', '%' . $term . '%')->orderBy('name')->limit(8)->get();

        if ($matches->count() === 1) {
            return $this->line($matches->first()->id);
        }

        if ($matches->isEmpty()) {
            throw ValidationException::withMessages(['scan' => "No product found for \"{$term}\". Add it on the Products screen first."]);
        }

        return response()->json([
            'results' => $matches->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
        ]);
    }

    public function store(Request $request, StockService $stock)
    {
        $lines = $request->input('lines');

        if (empty($lines) || ! is_array($lines)) {
            throw ValidationException::withMessages(['lines' => 'Add at least one product.']);
        }

        $request->validate([
            'lines.*.product_id' => 'required|string',
            'lines.*.unit_id' => 'nullable|string',
            'lines.*.quantity' => 'required|numeric|gt:0',
            'lines.*.line_total' => 'required|numeric|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'newSupplier' => 'nullable|string|max:150',
            'invoice_no' => 'nullable|string|max:50',
            'updateCostPrice' => 'nullable|boolean',
        ], [
            'lines.*.quantity.gt' => 'Quantity must be more than 0.',
            'lines.*.quantity.required' => 'Enter a quantity.',
            'lines.*.line_total.required' => 'Enter what you paid.',
        ]);

        // Work out each line again from the database: never trust the units the browser sent
        $resolved = [];
        $errors = [];

        foreach ($lines as $key => $line) {
            $product = Product::with(['unit', 'packagings' => fn ($q) => $q->where('is_active', true)])->find($line['product_id']);

            if (! $product) {
                $errors["lines.$key.quantity"] = 'This product no longer exists. Remove it from the list.';
                continue;
            }

            if (! $product->track_stock) {
                $errors["lines.$key.quantity"] = "{$product->name} does not track stock.";
                continue;
            }

            $unit = collect($this->units($product))->firstWhere('id', (string) ($line['unit_id'] ?? ''));

            if (! $unit) {
                $errors["lines.$key.unit_id"] = 'This packaging is no longer available. Choose another.';
                continue;
            }

            $resolved[] = [
                'product' => $product,
                'unit_name' => $unit['name'],
                'per' => (float) $unit['per'] ?: 1.0,
                'quantity' => (float) $line['quantity'],
                'line_total' => (float) $line['line_total'],
            ];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $user = $request->user();
        $updateCostPrice = $request->boolean('updateCostPrice');
        $total = round(collect($resolved)->sum('line_total'), 2);

        $receipt = DB::transaction(function () use ($request, $stock, $user, $resolved, $updateCostPrice, $total) {
            $supplierId = $request->input('supplier_id') ?: null;
            $newSupplier = trim((string) $request->input('newSupplier'));

            if ($newSupplier !== '') {
                $supplierId = Supplier::create(['name' => $newSupplier])->id;
            }

            // Numbered per branch (GRN-MAIN-261020-001) so branches syncing to one cloud never clash.
            // Locking the branch row stops two deliveries saved at once from taking the same number.
            $branch = Branch::whereKey($user->branch_id)->lockForUpdate()->firstOrFail();
            $prefix = 'GRN-' . $branch->code . '-' . now()->format('ymd') . '-';
            $todayCount = GoodsReceipt::where('receipt_no', 'like', $prefix . '%')->count() + 1;

            $receipt = GoodsReceipt::create([
                'receipt_no' => $prefix . str_pad($todayCount, 3, '0', STR_PAD_LEFT),
                'branch_id' => $user->branch_id,
                'supplier_id' => $supplierId,
                'user_id' => $user->id,
                'invoice_no' => trim((string) $request->input('invoice_no')) ?: null,
                'total' => $total,
                'received_at' => now(),
            ]);

            foreach ($resolved as $line) {
                $product = $line['product'];
                $qty = round($line['quantity'], 3);
                $baseQty = round($qty * $line['per'], 3);
                $lineTotal = round($line['line_total'], 2);
                $costPerBase = $baseQty > 0 ? $lineTotal / $baseQty : 0;
                $unitName = $line['unit_name'];

                $receipt->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_name' => $unitName,
                    'unit_quantity' => $qty,
                    'quantity' => $baseQty,
                    'unit_cost' => round($costPerBase, 2),
                    'line_total' => $lineTotal,
                ]);

                $stock->move(
                    product: $product,
                    branchId: $user->branch_id,
                    quantity: $baseQty,
                    type: 'purchase',
                    userId: $user->id,
                    unitCost: $costPerBase,
                    reference: $receipt,
                    reason: 'Delivery ' . $receipt->receipt_no . ' (' . rtrim(rtrim(number_format($qty, 3), '0'), '.') . ' x ' . $unitName . ')',
                );

                if ($updateCostPrice) {
                    $product->update(['cost_price' => round($costPerBase, 2)]);
                }
            }

            return $receipt;
        });

        $message = "Delivery {$receipt->receipt_no} saved: " . count($resolved) . ' product(s), KSh ' . number_format((float) $receipt->total, 2) . '.';

        if ($request->expectsJson()) {
            // Shown on the reloaded page so the recent deliveries list is fresh too
            session()->flash('status', $message);

            return response()->json(['message' => $message, 'redirect' => route('stock.receive')]);
        }

        return redirect()->route('stock.receive')->with('status', $message);
    }

    /** One delivery line for a product, like the old addLine(). */
    protected function line(string $productId, float $quantity = 1, ?string $unitId = null)
    {
        $product = Product::with(['unit', 'packagings' => fn ($q) => $q->where('is_active', true)->orderBy('quantity')])->find($productId);

        if (! $product) {
            throw ValidationException::withMessages(['scan' => 'Product not found.']);
        }

        if (! $product->track_stock) {
            throw ValidationException::withMessages(['scan' => "{$product->name} does not track stock."]);
        }

        $units = $this->units($product);
        $defaultId = $product->packagings->firstWhere('is_default_purchase', true)?->id;
        $chosen = collect($units)->firstWhere('id', (string) ($unitId ?? $defaultId)) ?? $units[0];

        return response()->json(['line' => [
            'product_id' => $product->id,
            'name' => $product->name,
            'base' => $units[0]['name'],
            'units' => $units,
            'unit_id' => $chosen['id'],
            'per' => $chosen['per'],
            'quantity' => $quantity,
            'cost_price' => (float) $product->cost_price,
            'line_total' => round($quantity * $chosen['per'] * (float) $product->cost_price, 2),
            'selling_price' => (float) $product->selling_price,
        ]]);
    }

    /** The ways a product can be bought: the base unit first ('' id), then each active packaging. */
    protected function units(Product $product): array
    {
        $base = $product->unit?->short_name ?? 'pc';
        $units = [['id' => '', 'name' => $base, 'per' => 1.0]];

        foreach ($product->packagings->sortBy('quantity') as $pk) {
            $units[] = ['id' => (string) $pk->id, 'name' => $pk->name . ' (' . rtrim(rtrim(number_format((float) $pk->quantity, 3), '0'), '.') . ' ' . $base . ')', 'per' => (float) $pk->quantity];
        }

        return $units;
    }
}
