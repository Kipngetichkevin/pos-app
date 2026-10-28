<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\MpesaTransaction;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductUnit;
use App\Models\SalePayment;
use App\Models\Shift;
use App\Models\StockLevel;
use App\Models\Till;
use App\Services\SaleService;
use App\Sync\SyncClient;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('pos.index', [
            'shift' => $this->openShiftFor($request),
            'tills' => Till::where('branch_id', $user->branch_id)->where('is_active', true)->orderBy('code')->get(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'isManager' => $user->isManager(),
        ]);
    }

    public function openShift(Request $request)
    {
        $data = $request->validate([
            'till_id' => 'required|exists:tills,id',
            'opening_float' => 'required|numeric|min:0',
        ]);

        if (Shift::where('till_id', $data['till_id'])->where('status', 'open')->exists()) {
            return back()->withInput()->withErrors(['till_id' => 'This till already has an open shift. Close it first.']);
        }

        $user = $request->user();

        Shift::create([
            'branch_id' => $user->branch_id,
            'till_id' => $data['till_id'],
            'user_id' => $user->id,
            'status' => 'open',
            'opened_at' => now(),
            'opening_float' => (float) $data['opening_float'],
        ]);

        return redirect()->route('pos');
    }

    /** Scan box: packaging barcode, product barcode, SKU, then name search. */
    public function lookup(Request $request)
    {
        $term = trim((string) $request->query('q'));

        if ($term === '') {
            return response()->json(['results' => []]);
        }

        // 1. Packaging barcode (e.g. a carton)
        $packaging = ProductUnit::with('product.unit')->where('barcode', $term)->where('is_active', true)->first();
        if ($packaging && $packaging->product?->is_active) {
            return response()->json(['add' => $packaging->selling_price !== null
                ? $this->item($request, $packaging->product, $packaging)
                : $this->item($request, $packaging->product, null, (float) $packaging->quantity)]);
        }

        // 2. Product barcode
        $barcode = ProductBarcode::with('product.unit')->where('barcode', $term)->first();
        if ($barcode && $barcode->product?->is_active) {
            return response()->json(['add' => $this->item($request, $barcode->product, null, (float) $barcode->pack_quantity)]);
        }

        // 3. SKU
        $bySku = Product::active()->where('sku', $term)->first();
        if ($bySku) {
            return response()->json(['pick' => $bySku->id]);
        }

        // 4. Name search
        $matches = Product::active()->where('name', 'like', '%' . $term . '%')->orderBy('name')->limit(8)->get();

        if ($matches->count() === 1) {
            return response()->json(['pick' => $matches->first()->id]);
        }

        if ($matches->isEmpty()) {
            throw ValidationException::withMessages(['scan' => "No product found for \"{$term}\"."]);
        }

        return response()->json([
            'results' => $matches->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'price' => (float) $p->selling_price])->values(),
        ]);
    }

    /** Product grid, optionally for one category. */
    public function products(Request $request)
    {
        $products = Product::active()->whereNull('parent_id')
            ->when($request->query('category'), fn ($q, $c) => $q->where('category_id', $c))
            ->withCount([
                'packs' => fn ($q) => $q->where('is_active', true),
                'packagings as sell_units_count' => fn ($q) => $q->where('is_active', true)->whereNotNull('selling_price'),
            ])
            ->with('unit')->orderBy('name')->limit(60)->get();

        return response()->json($products->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'price' => (float) $p->selling_price,
            'unit' => $p->unit?->short_name ?? 'pc',
            'options' => $p->packs_count + $p->sell_units_count,
        ])->values());
    }

    /**
     * Ways to sell a product: single unit, packagings (carton) and repacked packs (1 kg).
     * If there is only the single unit, the client adds it straight away.
     */
    public function options(Request $request, Product $product)
    {
        abort_unless($product->is_active, 404);

        $branchId = $request->user()->branch_id;

        $product->load([
            'unit',
            'stockLevels' => fn ($q) => $q->where('branch_id', $branchId),
            'packagings' => fn ($q) => $q->where('is_active', true)->whereNotNull('selling_price')->orderBy('quantity'),
            'packs' => fn ($q) => $q->where('is_active', true)->orderByDesc('pack_size')
                ->with(['unit', 'stockLevels' => fn ($s) => $s->where('branch_id', $branchId)]),
        ]);

        $baseStock = (float) ($product->stockLevels->first()?->quantity ?? 0);
        $unitName = $product->unit?->short_name ?? 'pc';
        $choices = [];

        if ((float) $product->selling_price > 0 || ($product->packagings->isEmpty() && $product->packs->isEmpty())) {
            $choices[] = [
                'label' => 'Single (' . $unitName . ')',
                'detail' => $product->track_stock ? $this->fq($baseStock) . ' ' . $unitName . ' in stock' : null,
                'warn' => $product->track_stock && $baseStock <= 0,
                'item' => $this->item($request, $product),
            ];
        }

        foreach ($product->packagings as $pk) {
            $choices[] = [
                'label' => $pk->name,
                'detail' => $this->fq($pk->quantity) . ' ' . $unitName
                    . ($product->track_stock ? ' · ' . floor(max($baseStock, 0) / (float) $pk->quantity) . ' available' : ''),
                'warn' => false,
                'item' => $this->item($request, $product, $pk),
            ];
        }

        foreach ($product->packs as $pack) {
            $onShelf = (float) ($pack->stockLevels->first()?->quantity ?? 0);
            $choices[] = [
                'label' => $pack->name,
                'detail' => $onShelf <= 0 ? 'None packed - repack first' : $this->fq($onShelf) . ' packed on the shelf',
                'warn' => $onShelf <= 0,
                'item' => $this->item($request, $pack),
            ];
        }

        return response()->json(['name' => $product->name, 'choices' => $choices]);
    }

    public function customers(Request $request)
    {
        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        return response()->json(
            Customer::where('is_active', true)
                ->where(fn ($q) => $q->where('name', 'like', '%' . $term . '%')->orWhere('phone', 'like', '%' . $term . '%'))
                ->orderBy('name')->limit(6)->get()
                ->map(fn ($c) => $this->customer($c))->values()
        );
    }

    public function storeCustomer(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:150',
            'phone' => 'nullable|string|max:20|unique:customers,phone',
        ], [
            'name.required' => 'Type the customer name first.',
            'name.min' => 'Type the customer name first.',
            'phone.unique' => 'Another customer already has this phone number.',
        ]);

        $customer = Customer::create([
            'name' => trim($data['name']),
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'credit_limit' => 0,
            'balance' => 0,
        ]);

        return response()->json($this->customer($customer));
    }

    public function checkout(Request $request, SaleService $sales)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|string',
            'items.*.unit_id' => 'nullable|string',
            'items.*.quantity' => 'required|numeric|gt:0',
            'cash' => 'nullable|numeric|min:0',
            'mpesa_amount' => 'nullable|numeric|min:0',
            'mpesa_code' => 'nullable|string|max:20',
            'mpesa_transaction_id' => 'nullable|string',
            'accept_unverified' => 'nullable|boolean',
            'customer_id' => 'nullable|string',
            'credit' => 'nullable|numeric|min:0',
        ], ['items.required' => 'The cart is empty.']);

        $user = $request->user();
        $shift = $this->openShiftFor($request);

        if (! $shift) {
            throw ValidationException::withMessages(['checkout' => 'Your shift is closed. Reload the page and open a shift.']);
        }

        // Grand total is worked out again by SaleService; this copy is only for the M-Pesa cap
        $total = $this->cartTotal($data['items'], ! empty($data['customer_id']) && Customer::whereKey($data['customer_id'])->value('price_level') === 'wholesale');
        $mpesa = min(round((float) ($data['mpesa_amount'] ?? 0), 2), $total);
        $code = strtoupper(trim((string) ($data['mpesa_code'] ?? '')));
        $verifiedTxId = null;
        $unverified = false;

        if ($mpesa > 0) {
            if ($code === '') {
                throw ValidationException::withMessages(['mpesaCode' => 'Enter the M-Pesa transaction code.']);
            }

            if (SalePayment::where('method', 'mpesa')->where('reference', $code)->exists()) {
                throw ValidationException::withMessages(['mpesaCode' => 'This M-Pesa code has already been used on another sale.']);
            }

            if (! preg_match('/^[A-Z0-9]{10}$/', $code)) {
                throw ValidationException::withMessages(['mpesaCode' => 'M-Pesa codes have 10 letters and numbers, e.g. UJEJ39YNJ2.']);
            }

            $tx = MpesaTransaction::where('mpesa_receipt', $code)->where('status', 'success')->first();

            // Branch server: the payment may be at the cloud and not synced here yet
            if (! $tx && SyncClient::enabled()) {
                try {
                    app(SyncClient::class)->fetchMpesa(receipt: $code);
                    $tx = MpesaTransaction::where('mpesa_receipt', $code)->where('status', 'success')->first();
                } catch (\Throwable $e) {
                    // offline: a manager can accept the code unverified
                }
            }

            if ($tx) {
                if ($tx->payments()->exists()) {
                    throw ValidationException::withMessages(['mpesaCode' => 'This M-Pesa payment is already attached to another sale.']);
                }

                // STK prompts are rounded up to whole shillings, so allow up to KSh 1 more than the amount due
                $isOwnPrompt = ($data['mpesa_transaction_id'] ?? null) === $tx->id;
                $diff = (float) $tx->amount - $mpesa;

                if (! ($isOwnPrompt ? $diff >= -0.009 && $diff < 1 : abs($diff) <= 0.009)) {
                    throw ValidationException::withMessages(['mpesaCode' => 'This M-Pesa payment was KSh ' . number_format((float) $tx->amount, 2) . ', but KSh ' . number_format($mpesa, 2) . ' was entered.']);
                }

                if ($tx->created_at->lt(now()->subDay())) {
                    throw ValidationException::withMessages(['mpesaCode' => 'This M-Pesa payment is more than 24 hours old.']);
                }

                $verifiedTxId = $tx->id;
            } elseif (! empty($data['accept_unverified']) && $user->isManager()) {
                $unverified = true;
            } else {
                throw ValidationException::withMessages(['mpesaCode' => 'This code is not in the M-Pesa records yet. Wait a few seconds and try again, and check the customer\'s SMS.']);
            }
        }

        $payments = [];

        if ((float) ($data['cash'] ?? 0) > 0) {
            $payments[] = ['method' => 'cash', 'amount' => (float) $data['cash'], 'reference' => null];
        }

        if ($mpesa > 0) {
            $payments[] = ['method' => 'mpesa', 'amount' => $mpesa, 'reference' => $code];
        }

        $credit = round((float) ($data['credit'] ?? 0), 2);
        $customerId = $data['customer_id'] ?? null;

        if ($credit > 0) {
            $customer = $customerId ? Customer::find($customerId) : null;

            if (! $customer) {
                throw ValidationException::withMessages(['checkout' => 'Choose the customer who is taking credit.']);
            }

            $limit = (float) $customer->credit_limit;
            $newBalance = (float) $customer->balance + $credit;

            if (! $user->isManager() && $limit <= 0) {
                throw ValidationException::withMessages(['checkout' => "{$customer->name} has no credit limit. A manager must set one on the Customers screen."]);
            }

            if (! $user->isManager() && $newBalance > $limit) {
                throw ValidationException::withMessages(['checkout' => "{$customer->name} would owe " . number_format($newBalance, 2) . ', above the limit of ' . number_format($limit, 2) . '.']);
            }

            $payments[] = ['method' => 'credit', 'amount' => $credit, 'reference' => null];
        }

        try {
            $sale = $sales->complete(
                items: collect($data['items'])->map(fn ($i) => [
                    'product_id' => $i['product_id'],
                    'quantity' => $i['quantity'],
                    'product_unit_id' => $i['unit_id'] ?? null,
                ])->values()->all(),
                payments: $payments,
                user: $user,
                shift: $shift,
                customerId: $customerId ?: null,
            );
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['checkout' => collect($e->errors())->flatten()->first()]);
        }

        if ($verifiedTxId) {
            $sale->payments()->where('method', 'mpesa')->update(['mpesa_transaction_id' => $verifiedTxId]);
        }

        if ($unverified) {
            $sale->payments()->where('method', 'mpesa')->update(['status' => 'pending']);
        }

        return response()->json([
            'id' => $sale->id,
            'receipt_no' => $sale->receipt_no,
            'change' => (float) $sale->change_given,
            'receipt_url' => route('receipts.show', $sale),
        ]);
    }

    // ---------------- Helpers ----------------

    protected function openShiftFor(Request $request): ?Shift
    {
        return Shift::with('till')
            ->where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    /** A cart line as the POS screen keeps it. Cost is only sent to managers. */
    protected function item(Request $request, Product $product, ?ProductUnit $unit = null, float $quantity = 1): array
    {
        $product->loadMissing('unit');

        $price = $unit ? (float) $unit->selling_price : (float) $product->selling_price;
        $baseCost = (float) StockLevel::where('branch_id', $request->user()->branch_id)->where('product_id', $product->id)->value('average_cost')
            ?: (float) $product->cost_price;
        $cost = round($baseCost * ($unit ? (float) $unit->quantity : 1), 2);

        return [
            'key' => $unit ? $product->id . '|' . $unit->id : $product->id,
            'product_id' => $product->id,
            'unit_id' => $unit?->id,
            'name' => $product->name . ($unit ? ' (' . $unit->name . ')' : ''),
            'price' => $price,
            'quantity' => $quantity,
            'unit' => $unit ? $unit->name : $product->unit?->short_name,
            'decimal' => $unit ? false : (bool) ($product->is_weighed || $product->unit?->allows_decimal),
            'below_cost' => $cost > $price,
            'cost' => $request->user()->isManager() ? $cost : null,
            // Packagings keep their own price; single units can drop to wholesale
            'wholesale_price' => $unit || $product->wholesale_price === null ? null : (float) $product->wholesale_price,
            'wholesale_min_qty' => $unit || $product->wholesale_min_qty === null ? null : (float) $product->wholesale_min_qty,
            'below_cost_wholesale' => ! $unit && $product->wholesale_price !== null && $cost > (float) $product->wholesale_price,
        ];
    }

    protected function customer(Customer $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phone,
            'balance' => (float) $c->balance,
            'credit_limit' => (float) $c->credit_limit,
            'price_level' => $c->price_level,
        ];
    }

    protected function cartTotal(array $items, bool $wholesaleCustomer = false): float
    {
        $products = Product::whereIn('id', collect($items)->pluck('product_id'))->get()->keyBy('id');
        $units = ProductUnit::whereIn('id', collect($items)->pluck('unit_id')->filter())->pluck('selling_price', 'id');

        return round(collect($items)->sum(function ($i) use ($products, $units, $wholesaleCustomer) {
            $qty = round((float) $i['quantity'], 3);
            $price = ! empty($i['unit_id'])
                ? (float) ($units[$i['unit_id']] ?? 0)
                : (float) $products->get($i['product_id'])?->priceFor($qty, $wholesaleCustomer);

            return round($price * $qty, 2);
        }), 2);
    }

    protected function fq($q): string
    {
        return rtrim(rtrim(number_format((float) $q, 3), '0'), '.');
    }
}

