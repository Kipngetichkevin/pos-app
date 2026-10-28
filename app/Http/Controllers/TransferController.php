<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockTransfer;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransferController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->user()->branch_id;

        return view('transfers.index', [
            'incoming' => StockTransfer::with(['fromBranch', 'sender'])->withCount('items')
                ->where('to_branch_id', $branchId)->where('status', 'sent')->latest('sent_at')->get(),
            'transfers' => StockTransfer::with(['fromBranch', 'toBranch', 'sender', 'receiver'])->withCount('items')
                ->where(fn ($q) => $q->where('from_branch_id', $branchId)->orWhere('to_branch_id', $branchId))
                ->latest('sent_at')->paginate(25),
            'branchId' => $branchId,
        ]);
    }

    public function create(Request $request)
    {
        return view('transfers.create', [
            'branches' => Branch::where('is_active', true)->where('id', '!=', $request->user()->branch_id)->orderBy('name')->get(),
        ]);
    }

    /** Send stock: it leaves this branch now and arrives when the other branch confirms. */
    public function store(Request $request, StockService $stock)
    {
        $user = $request->user();

        $data = $request->validate([
            'to_branch_id' => ['required', Rule::exists('branches', 'id')->where('is_active', true), Rule::notIn([$user->branch_id])],
            'notes' => 'nullable|string|max:255',
            'lines' => 'required|array|min:1',
            'lines.*.product_id' => 'required|exists:products,id',
            'lines.*.unit_id' => 'nullable|string',
            'lines.*.quantity' => 'required|numeric|gt:0',
        ], [
            'to_branch_id.required' => 'Choose the branch receiving the stock.',
            'to_branch_id.not_in' => 'You cannot send stock to your own branch.',
            'lines.required' => 'Add at least one product.',
            'lines.*.quantity.gt' => 'Quantity must be more than 0.',
        ]);

        // Work out base quantities (a carton of 12 = 12 pieces) from the product's own packagings
        $wanted = [];

        foreach ($data['lines'] as $key => $line) {
            $product = Product::with(['packagings' => fn ($q) => $q->where('is_active', true)])->find($line['product_id']);

            if (! $product->track_stock) {
                throw ValidationException::withMessages(["lines.{$key}.quantity" => "{$product->name} does not track stock."]);
            }

            $per = 1.0;

            if (! empty($line['unit_id'])) {
                $unit = $product->packagings->firstWhere('id', $line['unit_id']);

                if (! $unit) {
                    throw ValidationException::withMessages(["lines.{$key}.quantity" => 'This packaging is no longer available. Choose another.']);
                }

                $per = (float) $unit->quantity;
            }

            $wanted[$product->id] ??= ['product' => $product, 'qty' => 0, 'key' => $key];
            $wanted[$product->id]['qty'] = round($wanted[$product->id]['qty'] + (float) $line['quantity'] * $per, 3);
        }

        $transfer = DB::transaction(function () use ($data, $user, $wanted, $stock) {
            $branch = Branch::whereKey($user->branch_id)->lockForUpdate()->firstOrFail();
            $levels = StockLevel::where('branch_id', $branch->id)->whereIn('product_id', array_keys($wanted))->get()->keyBy('product_id');

            foreach ($wanted as $productId => $w) {
                $onHand = (float) ($levels[$productId]->quantity ?? 0);

                if ($w['qty'] > $onHand + 0.0005) {
                    throw ValidationException::withMessages(["lines.{$w['key']}.quantity" => "Only {$onHand} of {$w['product']->name} in stock here."]);
                }
            }

            $prefix = 'TR-' . $branch->code . '-' . now()->format('ymd') . '-';
            $count = StockTransfer::where('transfer_no', 'like', $prefix . '%')->count() + 1;

            $transfer = StockTransfer::create([
                'transfer_no' => $prefix . str_pad($count, 2, '0', STR_PAD_LEFT),
                'from_branch_id' => $branch->id,
                'to_branch_id' => $data['to_branch_id'],
                'status' => 'sent',
                'sent_by' => $user->id,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'sent_at' => now(),
            ]);

            $total = 0;

            foreach ($wanted as $productId => $w) {
                $cost = (float) ($levels[$productId]->average_cost ?? 0) ?: (float) $w['product']->cost_price;

                $transfer->items()->create([
                    'product_id' => $productId,
                    'product_name' => $w['product']->name,
                    'quantity_sent' => $w['qty'],
                    'unit_cost' => $cost,
                ]);

                $stock->move(
                    product: $w['product'],
                    branchId: $branch->id,
                    quantity: -$w['qty'],
                    type: 'transfer_out',
                    userId: $user->id,
                    unitCost: $cost,
                    reference: $transfer,
                    reason: 'Sent on ' . $transfer->transfer_no,
                );

                $total += $w['qty'] * $cost;
            }

            $transfer->update(['total_cost' => round($total, 2)]);

            return $transfer;
        });

        $message = "Transfer {$transfer->transfer_no} sent. The stock is out of this branch until the other branch confirms it arrived.";

        if ($request->expectsJson()) {
            session()->flash('status', $message);

            return response()->json(['message' => $message, 'redirect' => route('transfers.show', $transfer)]);
        }

        return redirect()->route('transfers.show', $transfer)->with('status', $message);
    }

    public function show(Request $request, StockTransfer $transfer)
    {
        abort_unless($transfer->involves($request->user()->branch_id), 404);

        return view('transfers.show', [
            'transfer' => $transfer->load(['fromBranch', 'toBranch', 'sender', 'receiver', 'items.product.unit']),
            'canReceive' => $transfer->status === 'sent' && $transfer->to_branch_id === $request->user()->branch_id,
            'canCancel' => $transfer->status === 'sent' && $transfer->from_branch_id === $request->user()->branch_id,
        ]);
    }

    /** Receiving branch confirms what actually arrived. Anything missing is recorded as lost in transit. */
    public function receive(Request $request, StockTransfer $transfer, StockService $stock)
    {
        $user = $request->user();
        abort_unless($transfer->to_branch_id === $user->branch_id, 404);

        if ($transfer->status !== 'sent') {
            return back()->withErrors(['general' => 'This transfer has already been ' . $transfer->status . '.']);
        }

        $data = $request->validate([
            'received' => 'required|array',
            'received.*' => 'required|numeric|min:0',
            'receive_notes' => 'nullable|string|max:255',
        ], ['received.*.required' => 'Enter how many arrived (0 if none).']);

        $items = $transfer->items()->with('product')->get();

        foreach ($items as $item) {
            $got = round((float) ($data['received'][$item->id] ?? -1), 3);

            if ($got < 0 || $got > (float) $item->quantity_sent + 0.0005) {
                return back()->withInput()->withErrors(["received.{$item->id}" => 'Between 0 and ' . (float) $item->quantity_sent . '.']);
            }
        }

        $shortfall = false;

        DB::transaction(function () use ($transfer, $items, $data, $user, $stock, &$shortfall) {
            foreach ($items as $item) {
                $got = round((float) $data['received'][$item->id], 3);
                $item->update(['quantity_received' => $got]);
                $shortfall = $shortfall || $got < (float) $item->quantity_sent;

                if ($got > 0) {
                    $stock->move(
                        product: $item->product,
                        branchId: $transfer->to_branch_id,
                        quantity: $got,
                        type: 'transfer_in',
                        userId: $user->id,
                        unitCost: (float) $item->unit_cost,
                        reference: $transfer,
                        reason: 'Received on ' . $transfer->transfer_no,
                    );
                }
            }

            $transfer->update([
                'status' => 'received',
                'received_by' => $user->id,
                'received_at' => now(),
                'receive_notes' => trim((string) ($data['receive_notes'] ?? '')) ?: null,
            ]);
        });

        return redirect()->route('transfers.show', $transfer)->with(
            $shortfall ? 'warning' : 'status',
            $shortfall ? "Transfer {$transfer->transfer_no} received with items missing. The missing stock is recorded as lost in transit."
                : "Transfer {$transfer->transfer_no} received. Stock added to this branch."
        );
    }

    /** Sender takes it back before it is received (e.g. the vehicle did not leave). */
    public function cancel(Request $request, StockTransfer $transfer, StockService $stock)
    {
        $user = $request->user();
        abort_unless($transfer->from_branch_id === $user->branch_id, 404);

        if ($transfer->status !== 'sent') {
            return back()->withErrors(['general' => 'Only transfers that have not been received can be cancelled.']);
        }

        DB::transaction(function () use ($transfer, $user, $stock) {
            foreach ($transfer->items()->with('product')->get() as $item) {
                $stock->move(
                    product: $item->product,
                    branchId: $transfer->from_branch_id,
                    quantity: (float) $item->quantity_sent,
                    type: 'transfer_in',
                    userId: $user->id,
                    unitCost: (float) $item->unit_cost,
                    reference: $transfer,
                    reason: 'Cancelled ' . $transfer->transfer_no,
                );
            }

            $transfer->update(['status' => 'cancelled']);
        });

        return redirect()->route('transfers.show', $transfer)->with('status', "Transfer {$transfer->transfer_no} cancelled. The stock is back in this branch.");
    }
}
