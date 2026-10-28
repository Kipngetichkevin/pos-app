<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public const REASONS = [
        'damaged' => 'Damaged',
        'expired' => 'Expired',
        'theft' => 'Theft / loss',
        'count' => 'Stock count correction',
        'own_use' => 'Own use',
        'found' => 'Found / extra stock',
        'other' => 'Other',
    ];

    public const FILTERS = ['all', 'low', 'out', 'inactive'];

    public function index(Request $request)
    {
        $branchId = $request->user()->branch_id;
        $stockSql = $this->stockSql();
        $search = trim((string) $request->query('search'));
        $filter = in_array($request->query('filter'), self::FILTERS, true) ? $request->query('filter') : 'all';

        $products = Product::query()
            ->where('track_stock', true)
            ->with(['unit', 'category', 'parent', 'stockLevels' => fn ($q) => $q->where('branch_id', $branchId)])
            ->withCount('packs')
            ->with(['packagings' => fn ($q) => $q->where('is_active', true)->orderByDesc('quantity')])
            ->when($search, function ($query) use ($search) {
                $term = '%' . $search . '%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhereHas('barcodes', fn ($b) => $b->where('barcode', 'like', $term)));
            })
            ->when($filter === 'low', fn ($q) => $q->whereRaw("{$stockSql} <= products.reorder_level", [$branchId]))
            ->when($filter === 'out', fn ($q) => $q->whereRaw("{$stockSql} <= 0", [$branchId]))
            ->when($filter !== 'inactive', fn ($q) => $q->where('is_active', true))
            ->when($filter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $levels = StockLevel::query()
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->where('stock_levels.branch_id', $branchId)
            ->where('products.track_stock', true)
            ->where('products.is_active', true)
            ->where('stock_levels.quantity', '>', 0);

        $activeTracked = Product::where('track_stock', true)->where('is_active', true);

        $summary = [
            'cost_value' => (float) (clone $levels)->sum(DB::raw('stock_levels.quantity * stock_levels.average_cost')),
            'retail_value' => (float) (clone $levels)->sum(DB::raw('stock_levels.quantity * products.selling_price')),
            'low' => (clone $activeTracked)->whereRaw("{$stockSql} <= products.reorder_level", [$branchId])->count(),
            'out' => (clone $activeTracked)->whereRaw("{$stockSql} <= 0", [$branchId])->count(),
        ];

        // ?adjust=ID opens the adjust form, ?history=ID opens the stock history
        $adjusting = $request->query('adjust') ? Product::with('unit')->find($request->query('adjust')) : null;
        $historyProduct = $request->query('history') ? Product::with('unit')->find($request->query('history')) : null;

        return view('stock.index', [
            'products' => $products,
            'search' => $search,
            'filter' => $filter,
            'summary' => $summary,
            'reasons' => self::REASONS,
            'adjusting' => $adjusting,
            'adjustCurrent' => $adjusting
                ? (float) StockLevel::where('branch_id', $branchId)->where('product_id', $adjusting->id)->value('quantity')
                : 0,
            'historyProduct' => $historyProduct,
            'movements' => $historyProduct
                ? StockMovement::with('user')->where('branch_id', $branchId)->where('product_id', $historyProduct->id)->latest()->limit(100)->get()
                : collect(),
            // Where the popups close to: the list with the current search, filter and page
            'listUrl' => route('stock.index', $request->only(['search', 'filter', 'page'])),
        ]);
    }

    public function adjust(Request $request, Product $product, StockService $stock)
    {
        $request->validate([
            'adjustQty' => 'required|numeric|min:0',
            'adjustMode' => 'required|in:add,remove,set',
            'adjustReason' => 'required|in:' . implode(',', array_keys(self::REASONS)),
            'adjustNote' => $request->input('adjustReason') === 'other' ? 'required|string|max:200' : 'nullable|string|max:200',
        ], [
            'adjustNote.required' => 'Please describe the reason.',
        ]);

        $user = $request->user();
        $product->load('unit');
        $qty = (float) $request->input('adjustQty');
        $mode = $request->input('adjustMode');
        $reasonKey = $request->input('adjustReason');
        $note = (string) $request->input('adjustNote');

        if (! ($product->is_weighed || $product->unit?->allows_decimal) && floor($qty) != $qty) {
            return back()->withInput()->withErrors(['adjustQty' => 'This product is counted in whole units.']);
        }

        $current = (float) StockLevel::where('branch_id', $user->branch_id)->where('product_id', $product->id)->value('quantity');

        $change = match ($mode) {
            'add' => $qty,
            'remove' => -$qty,
            default => $qty - $current,
        };

        if (round($change, 3) == 0) {
            return back()->withInput()->withErrors(['adjustQty' => 'This would not change the stock.']);
        }

        $reason = self::REASONS[$reasonKey] . ($note ? ': ' . trim($note) : '');

        $stock->move(
            product: $product,
            branchId: $user->branch_id,
            quantity: round($change, 3),
            type: in_array($reasonKey, ['damaged', 'expired']) ? 'damage' : 'adjustment',
            userId: $user->id,
            reason: $reason,
        );

        $message = "{$product->name}: stock " . ($change > 0 ? 'increased' : 'reduced') . ' by ' . rtrim(rtrim(number_format(abs($change), 3), '0'), '.') . '.';

        return redirect($this->listUrl())->with('status', $message);
    }

    protected function stockSql(): string
    {
        return 'COALESCE((SELECT quantity FROM stock_levels WHERE stock_levels.product_id = products.id AND stock_levels.branch_id = ? LIMIT 1), 0)';
    }

    /** The stock list the user came from, without the adjust popup open. */
    protected function listUrl(): string
    {
        parse_str((string) parse_url(url()->previous(), PHP_URL_QUERY), $query);

        return route('stock.index', array_intersect_key($query, array_flip(['search', 'filter', 'page'])));
    }
}
