<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockTake;
use App\Models\StockTakeItem;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockTakeController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->user()->branch_id;

        return view('stock-takes.index', [
            'takes' => StockTake::with(['category', 'user'])->withCount([
                'items',
                'items as counted_count' => fn ($q) => $q->whereNotNull('counted_qty'),
            ])->where('branch_id', $branchId)->latest('started_at')->paginate(20),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            'open' => StockTake::where('branch_id', $branchId)->where('status', 'counting')->first(),
        ]);
    }

    /** Start counting: list every tracked product (or one category) with its cost. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'notes' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        if (StockTake::where('branch_id', $user->branch_id)->where('status', 'counting')->exists()) {
            return back()->withErrors(['general' => 'A stock take is already in progress. Finish or cancel it first.']);
        }

        $take = DB::transaction(function () use ($data, $user) {
            $branch = Branch::whereKey($user->branch_id)->lockForUpdate()->firstOrFail();
            $prefix = 'ST-' . $branch->code . '-' . now()->format('ymd') . '-';
            $count = StockTake::where('take_no', 'like', $prefix . '%')->count() + 1;

            $take = StockTake::create([
                'take_no' => $prefix . str_pad($count, 2, '0', STR_PAD_LEFT),
                'branch_id' => $branch->id,
                'category_id' => $data['category_id'] ?? null,
                'user_id' => $user->id,
                'status' => 'counting',
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'started_at' => now(),
            ]);

            $costs = StockLevel::where('branch_id', $branch->id)->pluck('average_cost', 'product_id');

            Product::where('track_stock', true)->where('is_active', true)
                ->when($take->category_id, fn ($q, $c) => $q->where('category_id', $c))
                ->orderBy('name')
                ->each(function (Product $product) use ($take, $costs) {
                    $take->items()->create([
                        'product_id' => $product->id,
                        'unit_cost' => (float) ($costs[$product->id] ?? 0) ?: (float) $product->cost_price,
                    ]);
                });

            return $take;
        });

        if ($take->items()->doesntExist()) {
            $take->update(['status' => 'cancelled']);

            return back()->withErrors(['general' => 'There are no stock-tracked products to count.']);
        }

        return redirect()->route('stock-takes.show', $take)->with('status', "Stock take {$take->take_no} started. Count the shelves and enter what you find.");
    }

    public function show(Request $request, StockTake $stockTake)
    {
        abort_unless($stockTake->branch_id === $request->user()->branch_id, 404);

        $search = trim((string) $request->query('search'));
        $filter = $request->query('filter', $stockTake->isCounting() ? 'uncounted' : 'variance');

        $items = $stockTake->items()
            ->with(['product.unit', 'countedBy'])
            ->join('products', 'products.id', '=', 'stock_take_items.product_id')
            ->select('stock_take_items.*')
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('products.name', 'like', "%{$search}%")
                ->orWhere('products.sku', $search)
                ->orWhereExists(fn ($b) => $b->from('product_barcodes')->whereColumn('product_barcodes.product_id', 'products.id')->where('barcode', $search))))
            ->when($filter === 'uncounted', fn ($q) => $q->whereNull('counted_qty'))
            ->when($filter === 'counted', fn ($q) => $q->whereNotNull('counted_qty'))
            ->when($filter === 'variance', fn ($q) => $q->whereNotNull('variance')->where('variance', '!=', 0))
            ->orderBy('products.name')
            ->paginate(50)
            ->withQueryString();

        $all = $stockTake->items();

        return view('stock-takes.show', [
            'take' => $stockTake->load(['category', 'user', 'completedBy']),
            'items' => $items,
            'search' => $search,
            'filter' => $filter,
            'progress' => [
                'total' => (clone $all)->count(),
                'counted' => (clone $all)->whereNotNull('counted_qty')->count(),
                'with_variance' => (clone $all)->whereNotNull('variance')->where('variance', '!=', 0)->count(),
                'shortage' => (float) (clone $all)->where('variance', '<', 0)->sum(DB::raw('variance * unit_cost')),
                'excess' => (float) (clone $all)->where('variance', '>', 0)->sum(DB::raw('variance * unit_cost')),
            ],
            'levels' => StockLevel::where('branch_id', $stockTake->branch_id)
                ->whereIn('product_id', $items->pluck('product_id'))
                ->pluck('quantity', 'product_id'),
        ]);
    }

    /** Save the counts typed on the current page. Blank boxes are left as they were. */
    public function count(Request $request, StockTake $stockTake)
    {
        abort_unless($stockTake->branch_id === $request->user()->branch_id, 404);

        if (! $stockTake->isCounting()) {
            return back()->withErrors(['general' => 'This stock take is finished.']);
        }

        $data = $request->validate([
            'counts' => 'required|array',
            'counts.*' => 'nullable|numeric|min:0',
        ], ['counts.*.min' => 'A count cannot be negative.']);

        $items = $stockTake->items()->with('product.unit')
            ->whereIn('id', array_keys($data['counts']))
            ->get()->keyBy('id');

        $levels = StockLevel::where('branch_id', $stockTake->branch_id)
            ->whereIn('product_id', $items->pluck('product_id'))
            ->pluck('quantity', 'product_id');

        $saved = 0;
        $errors = [];

        foreach ($data['counts'] as $id => $value) {
            $item = $items->get($id);

            if (! $item || $value === null || $value === '') {
                continue;
            }

            $counted = round((float) $value, 3);
            $product = $item->product;

            if (! ($product->is_weighed || $product->unit?->allows_decimal) && floor($counted) != $counted) {
                $errors["counts.{$id}"] = "{$product->name} is counted in whole units.";
                continue;
            }

            $expected = round((float) ($levels[$item->product_id] ?? 0), 3);

            $item->update([
                'expected_qty' => $expected,
                'counted_qty' => $counted,
                'variance' => round($counted - $expected, 3),
                'counted_by' => $request->user()->id,
                'counted_at' => now(),
            ]);

            $saved++;
        }

        $back = back()->with('status', "Saved {$saved} count(s).");

        return $errors ? $back->withInput()->withErrors($errors) : $back;
    }

    /** Post the differences to stock. Products not counted are left unchanged. */
    public function complete(Request $request, StockTake $stockTake, StockService $stock)
    {
        abort_unless($stockTake->branch_id === $request->user()->branch_id, 404);

        if (! $stockTake->isCounting()) {
            return back()->withErrors(['general' => 'This stock take is already finished.']);
        }

        if ($stockTake->items()->whereNotNull('counted_qty')->doesntExist()) {
            return back()->withErrors(['general' => 'Nothing has been counted yet.']);
        }

        $user = $request->user();

        DB::transaction(function () use ($stockTake, $stock, $user) {
            $shortage = 0;
            $excess = 0;

            $stockTake->items()->with('product')->whereNotNull('variance')->where('variance', '!=', 0)
                ->each(function (StockTakeItem $item) use ($stockTake, $stock, $user, &$shortage, &$excess) {
                    $stock->move(
                        product: $item->product,
                        branchId: $stockTake->branch_id,
                        quantity: (float) $item->variance,
                        type: 'adjustment',
                        userId: $user->id,
                        unitCost: (float) $item->unit_cost,
                        reference: $stockTake,
                        reason: 'Stock take ' . $stockTake->take_no,
                    );

                    $item->varianceValue() < 0 ? $shortage += -$item->varianceValue() : $excess += $item->varianceValue();
                });

            $stockTake->update([
                'status' => 'completed',
                'completed_by' => $user->id,
                'completed_at' => now(),
                'shortage_value' => round($shortage, 2),
                'excess_value' => round($excess, 2),
            ]);
        });

        return redirect()->route('stock-takes.show', ['stockTake' => $stockTake, 'filter' => 'variance'])
            ->with('status', "Stock take {$stockTake->take_no} completed. Stock levels now match the count.");
    }

    public function cancel(Request $request, StockTake $stockTake)
    {
        abort_unless($stockTake->branch_id === $request->user()->branch_id, 404);

        if ($stockTake->isCounting()) {
            $stockTake->update(['status' => 'cancelled']);
        }

        return redirect()->route('stock-takes.index')->with('status', "Stock take {$stockTake->take_no} cancelled. No stock was changed.");
    }
}
