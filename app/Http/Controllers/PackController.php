<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Repack;
use App\Models\StockLevel;
use App\Models\Unit;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PackController extends Controller
{
    public function index(Request $request, Product $product)
    {
        abort_if($product->parent_id, 404);

        $branchId = $request->user()->branch_id;

        $packs = $product->packs()
            ->with(['barcodes', 'stockLevels' => fn ($q) => $q->where('branch_id', $branchId)])
            ->orderByDesc('is_active')
            ->orderByDesc('pack_size')
            ->get();

        return view('stock.packs', [
            'product' => $product,
            'bulk' => $this->bulkLevel($product),
            'packs' => $packs,
            'unit' => $product->unit?->short_name,
            'repacks' => Repack::with('user')->where('product_id', $product->id)->latest()->limit(10)->get(),
        ]);
    }

    public function store(Request $request, Product $product)
    {
        abort_if($product->parent_id, 404);

        $data = $request->validate([
            'label' => 'required|string|max:30',
            'size' => 'required|numeric|gt:0',
            'price' => 'required|numeric|min:0',
            'barcode' => ['nullable', 'string', 'max:50', Rule::unique('product_barcodes', 'barcode')],
        ], [
            'label.required' => 'Give the size a name, e.g. 1 kg or 500 ml.',
            'barcode.unique' => 'This barcode is already used by another product.',
        ]);

        $bulk = $this->bulkLevel($product);

        $pack = Product::create([
            'parent_id' => $product->id,
            'pack_size' => (float) $data['size'],
            'name' => $product->name . ' ' . trim($data['label']),
            'sku' => 'P-' . strtoupper(Str::random(8)),
            'category_id' => $product->category_id,
            'unit_id' => Unit::where('short_name', 'pc')->value('id') ?? $product->unit_id,
            'tax_rate_id' => $product->tax_rate_id,
            'cost_price' => round($bulk['avg'] * (float) $data['size'], 2),
            'selling_price' => (float) $data['price'],
            'track_stock' => true,
            'is_active' => true,
        ]);

        $barcode = trim((string) ($data['barcode'] ?? ''));

        if ($barcode !== '') {
            $pack->barcodes()->create(['barcode' => $barcode, 'pack_quantity' => 1]);
        }

        return redirect()->route('stock.packs', $product)->with('status', "Added {$pack->name}.");
    }

    public function update(Request $request, Product $product, Product $pack)
    {
        $this->ensurePackOf($product, $pack);

        $price = $request->input('selling_price');

        // Same as before: an empty or invalid price is simply ignored
        if (! is_numeric($price) || (float) $price < 0) {
            return redirect()->route('stock.packs', $product);
        }

        $pack->update(['selling_price' => round((float) $price, 2)]);

        return redirect()->route('stock.packs', $product)->with('status', "{$pack->name} price updated.");
    }

    public function toggle(Product $product, Product $pack)
    {
        $this->ensurePackOf($product, $pack);

        $pack->update(['is_active' => ! $pack->is_active]);

        return redirect()->route('stock.packs', $product)
            ->with('status', $pack->name . ($pack->is_active ? ' activated.' : ' hidden from the POS.'));
    }

    public function repack(Request $request, Product $product, StockService $stock)
    {
        abort_if($product->parent_id, 404);

        $counts = (array) $request->input('counts', []);
        $bulkUsed = (string) $request->input('bulkUsed', '');

        $packs = $product->packs()->where('is_active', true)->get()->keyBy('id');
        $lines = collect($counts)->filter(fn ($count, $id) => is_scalar($count) && (int) $count > 0 && $packs->has($id));

        if ($lines->isEmpty()) {
            return back()->withInput()->withErrors(['counts' => 'Enter how many packs of each size you made.']);
        }

        // Like before, the packed total is worked out from every count entered
        $packed = $this->packedQuantity(collect($counts)->filter(fn ($count) => is_scalar($count)), $packs);
        $used = $bulkUsed !== '' && is_numeric($bulkUsed) ? round((float) $bulkUsed, 3) : $packed;
        $bulk = $this->bulkLevel($product);

        if ($used < $packed - 0.0005) {
            return back()->withInput()->withErrors(['bulkUsed' => 'The amount taken from the bulk cannot be less than what was packed (' . $packed . ').']);
        }

        if ($used > $bulk['qty'] + 0.0005) {
            return back()->withInput()->withErrors(['bulkUsed' => 'Only ' . $bulk['qty'] . ' ' . $product->unit?->short_name . ' in stock. Receive the bulk stock first.']);
        }

        $user = $request->user();
        $costTotal = round($used * $bulk['avg'], 2);
        // Spread the full cost (including any loss) over what was actually packed
        $costPerBaseUnit = $packed > 0 ? $costTotal / $packed : 0;

        DB::transaction(function () use ($stock, $user, $product, $packs, $lines, $packed, $used, $bulk, $costTotal, $costPerBaseUnit) {
            $repack = Repack::create([
                'branch_id' => $user->branch_id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'bulk_used' => $used,
                'packed_quantity' => $packed,
                'cost_total' => $costTotal,
            ]);

            $stock->move(
                product: $product,
                branchId: $user->branch_id,
                quantity: -$used,
                type: 'repack_out',
                userId: $user->id,
                unitCost: $bulk['avg'],
                reference: $repack,
                reason: 'Repacked into packs',
            );

            $detail = [];

            foreach ($lines as $id => $count) {
                $pack = $packs->get($id);
                $count = (int) $count;
                $unitCost = round($costPerBaseUnit * (float) $pack->pack_size, 2);

                $stock->move(
                    product: $pack,
                    branchId: $user->branch_id,
                    quantity: $count,
                    type: 'repack_in',
                    userId: $user->id,
                    unitCost: $unitCost,
                    reference: $repack,
                    reason: 'Repacked from ' . $product->name,
                );

                $pack->update(['cost_price' => $unitCost]);

                $detail[] = ['product_id' => $pack->id, 'name' => $pack->name, 'count' => $count, 'unit_cost' => $unitCost];
            }

            $repack->update(['packs' => $detail]);
        });

        $loss = round($used - $packed, 3);
        $message = 'Repack saved: ' . $used . ' ' . $product->unit?->short_name . ' used'
            . ($loss > 0 ? ' (repacking loss ' . $loss . ')' : '') . '.';

        return redirect()->route('stock.packs', $product)->with('status', $message);
    }

    protected function ensurePackOf(Product $product, Product $pack): void
    {
        abort_if($product->parent_id, 404);
        abort_unless($pack->parent_id === $product->id, 404);
    }

    protected function bulkLevel(Product $product): array
    {
        $level = StockLevel::where('branch_id', auth()->user()->branch_id)
            ->where('product_id', $product->id)
            ->first();

        return [
            'qty' => (float) ($level?->quantity ?? 0),
            'avg' => (float) ($level?->average_cost ?: $product->cost_price),
        ];
    }

    protected function packedQuantity(Collection $counts, Collection $packs): float
    {
        return round($counts->sum(function ($count, $id) use ($packs) {
            $pack = $packs->get($id);
            return $pack ? (int) $count * (float) $pack->pack_size : 0;
        }), 3);
    }
}
