<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();
        $category = (string) $request->query('category');

        $query = Expense::query()
            ->where('branch_id', $user->branch_id)
            ->whereBetween('spent_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])
            // Cashiers see only what they recorded
            ->when(! $user->isManager(), fn ($q) => $q->where('user_id', $user->id))
            ->when($category, fn ($q) => $q->where('category', $category));

        return view('expenses.index', [
            'expenses' => (clone $query)->with('user')->latest('spent_at')->paginate(25)->withQueryString(),
            'total' => (float) (clone $query)->sum('amount'),
            'byCategory' => (clone $query)->selectRaw('category, SUM(amount) as total')->groupBy('category')->orderByDesc('total')->pluck('total', 'category'),
            'from' => Carbon::parse($from)->toDateString(),
            'to' => Carbon::parse($to)->toDateString(),
            'category' => $category,
            'shift' => $this->openShift($user),
            'showForm' => $request->boolean('create'),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(Expense::CATEGORIES))],
            'description' => 'required|string|max:200',
            'paid_to' => 'nullable|string|max:100',
            'amount' => 'required|numeric|gt:0|max:10000000',
            'method' => ['required', Rule::in(array_keys(Expense::METHODS))],
            'reference' => 'required_if:method,mpesa|nullable|string|max:50',
            'spent_at' => 'nullable|date|before_or_equal:now',
        ], [
            'reference.required_if' => 'Enter the M-Pesa code.',
            'description.required' => 'Say what the money was for.',
        ]);

        $shift = $this->openShift($user);

        // Cash comes out of the drawer, so it must be against the open shift to balance the cash-up
        if ($data['method'] === 'cash' && ! $shift) {
            return back()->withInput()->withErrors(['method' => 'Open a shift on the POS first: cash expenses come out of your till.']);
        }

        $expense = Expense::create([
            'branch_id' => $user->branch_id,
            'shift_id' => $data['method'] === 'cash' ? $shift->id : null,
            'user_id' => $user->id,
            'category' => $data['category'],
            'description' => trim($data['description']),
            'paid_to' => trim((string) ($data['paid_to'] ?? '')) ?: null,
            'amount' => round((float) $data['amount'], 2),
            'method' => $data['method'],
            'reference' => strtoupper(trim((string) ($data['reference'] ?? ''))) ?: null,
            // Only managers may back-date an expense
            'spent_at' => $user->isManager() && ! empty($data['spent_at']) ? Carbon::parse($data['spent_at']) : now(),
        ]);

        return redirect()->route('expenses.index')
            ->with('status', 'Recorded ' . $expense->categoryLabel() . ': KSh ' . number_format((float) $expense->amount, 2) . '.');
    }

    
    public function verifyMpesa(Request $request)
    {
        $code = strtoupper(trim((string) $request->query('code')));
        
        if (strlen($code) !== 10) {
            return response()->json(['valid' => false, 'message' => 'Code must be 10 characters.']);
        }

        $tx = \App\Models\MpesaTransaction::where('mpesa_receipt', $code)->where('status', 'success')->first();

        if (! $tx) {
            return response()->json(['valid' => false, 'message' => 'Not found in M-Pesa records.']);
        }

        if ($tx->payments()->exists() || \App\Models\Expense::where('mpesa_transaction_id', $tx->id)->exists()) {
            return response()->json(['valid' => false, 'message' => 'This code has already been used.']);
        }

        return response()->json([
            'valid' => true, 
            'amount' => (float) $tx->amount,
            'message' => 'Verified! KSh ' . number_format((float) $tx->amount, 2)
        ]);
    }
    public function destroy(Request $request, Expense $expense)
    {
        abort_unless($expense->branch_id === $request->user()->branch_id, 404);
        abort_unless($request->user()->isManager(), 403);

        // A cash expense on a closed shift is part of a finished cash-up and cannot be removed
        if ($expense->shift_id && $expense->shift?->status !== 'open') {
            return back()->withErrors(['general' => 'This expense is on a closed shift and cannot be removed.']);
        }

        $expense->delete();

        return back()->with('status', 'Expense removed.');
    }

    protected function openShift(User $user): ?Shift
    {
        return Shift::where('user_id', $user->id)->where('status', 'open')->latest('opened_at')->first();
    }
}

