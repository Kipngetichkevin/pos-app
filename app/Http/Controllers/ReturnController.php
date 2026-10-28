<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Shift;
use App\Services\ReturnService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReturnController extends Controller
{
    public function __construct(private ReturnService $returns)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $shift = Shift::where('user_id', $user->id)->where('status', 'open')->latest('opened_at')->first();
        $sale = null;

        // ?receipt=MAIN-T01-000012 finds by receipt number, ?sale=ID opens one from the list
        if ($request->filled('receipt')) {
            $receiptNo = strtoupper(trim($request->query('receipt')));
            $sale = Sale::where('receipt_no', $receiptNo)->first();

            if (! $sale) {
                return redirect()->route('returns')->withErrors(['receiptNo' => "No sale with receipt number {$receiptNo}."])->withInput(['receipt' => $receiptNo]);
            }

            return redirect()->route('returns', ['sale' => $sale->id]);
        }

        if ($request->filled('sale')) {
            $sale = Sale::with(['items', 'payments', 'cashier', 'returns', 'shift'])->find($request->query('sale'));
        }

        return view('returns.index', [
            'shift' => $shift,
            'sale' => $sale,
            'recentSales' => $shift ? Sale::where('shift_id', $shift->id)->latest('sold_at')->limit(30)->get() : collect(),
            'reasons' => ReturnService::REASONS,
            'needsPin' => ! $user->isManager(),
            'canVoid' => $sale ? $this->returns->canVoid($sale) : false,
        ]);
    }

    public function store(Request $request, Sale $sale)
    {
        $data = $request->validate([
            'qty' => 'nullable|array',
            'qty.*' => 'nullable|numeric|min:0',
            'restock' => 'nullable|array',
            'reason' => ['required', Rule::in(ReturnService::REASONS)],
            'refundMethod' => 'required|in:cash,mpesa,credit',
            'refundReference' => 'required_if:refundMethod,mpesa|nullable|string|max:50',
            'pin' => 'nullable|string|max:10',
        ], [
            'reason.required' => 'Choose why the items are coming back.',
            'refundReference.required_if' => 'Enter the M-Pesa code of the money sent back.',
        ]);

        try {
            $approver = $this->returns->approver($request->user(), $data['pin'] ?? null);

            $return = $this->returns->process(
                sale: $sale,
                quantities: $data['qty'] ?? [],
                restock: collect($data['restock'] ?? [])->map(fn ($v) => (bool) $v)->all(),
                reason: $data['reason'],
                method: $data['refundMethod'],
                reference: $data['refundReference'] ?? null,
                user: $request->user(),
                approver: $approver,
            );
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('returns', ['sale' => $sale->id])
            ->with('status', 'Return ' . $return->return_no . ' saved. Refund KSh ' . number_format((float) $return->total_refunded, 2) . ' by ' . strtoupper($return->refund_method) . '.')
            ->with('status_link', ['url' => route('returns.receipt', $return), 'label' => 'Print return receipt']);
    }

    public function void(Request $request, Sale $sale)
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(ReturnService::REASONS)],
            'pin' => 'nullable|string|max:10',
        ], ['reason.required' => 'Choose why the sale is being voided.']);

        try {
            $approver = $this->returns->approver($request->user(), $data['pin'] ?? null);
            $this->returns->void($sale, $data['reason'], $request->user(), $approver);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('returns', ['sale' => $sale->id])->with('status', "Sale {$sale->receipt_no} voided. All items are back in stock.");
    }

    public function receipt(SaleReturn $saleReturn)
    {
        $saleReturn->load(['sale.branch', 'items.saleItem', 'user', 'approvedBy']);

        return view('returns.receipt', [
            'return' => $saleReturn,
            'creditNote' => \App\Models\EtimsInvoice::where('sale_return_id', $saleReturn->id)->first(),
        ]);
    }
}
