<?php

namespace App\Http\Controllers;

use App\Models\MpesaTransaction;
use App\Models\Shift;
use App\Services\MpesaService;
use App\Sync\SyncClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

class MpesaController extends Controller
{
    /** Send an STK prompt to the customer's phone. */
    public function stk(Request $request, MpesaService $mpesa, SyncClient $sync)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:20',
            'amount' => 'required|numeric|gt:0',
        ], [
            'phone.required' => 'Enter the customer\'s phone number.',
            'amount.gt' => 'Add items first. There is nothing to pay by M-Pesa.',
        ]);

        $shift = Shift::where('user_id', $request->user()->id)->where('status', 'open')->latest('opened_at')->first();

        if (! $shift) {
            throw ValidationException::withMessages(['mpesaPhone' => 'Open a shift first.']);
        }

        try {
            $tx = $mpesa->stkPush($data['phone'], (float) $data['amount'], $shift->branch_id, $shift->till_id, 'POS');
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['mpesaPhone' => $e->getMessage()]);
        }

        // Branch server: Safaricom calls the cloud back, so the cloud must know this prompt right away
        if (SyncClient::enabled()) {
            try {
                $sync->pushRows('mpesa_transactions', [$tx->id]);
            } catch (Throwable $e) {
                // the cashier can still type the code from the customer's SMS
            }
        }

        return response()->json(['id' => $tx->id, 'amount' => (float) $tx->amount, 'phone' => $tx->phone]);
    }

    /**
     * Polled every 2 seconds while the customer enters their PIN.
     * If Safaricom's callback is late, ask Safaricom directly (after 15 s, at most every 10 s).
     */
    public function status(MpesaTransaction $transaction, MpesaService $mpesa, SyncClient $sync)
    {
        $tx = $transaction;

        // Branch server: the result arrives at the cloud, so fetch it from there
        if ($tx->status === 'pending' && SyncClient::enabled() && Cache::add('mpesa-cloud:' . $tx->id, true, 3)) {
            try {
                $sync->fetchMpesa(id: $tx->id);
                $tx = $tx->fresh();
            } catch (Throwable $e) {
                // offline: fall back to asking Safaricom below
            }
        }

        $age = $tx->created_at->diffInSeconds(now());

        if ($tx->status === 'pending' && $age >= 15 && Cache::add('mpesa-query:' . $tx->id, true, 10)) {
            try {
                $tx = $mpesa->queryStatus($tx);
            } catch (Throwable $e) {
                // keep waiting
            }
        }

        if ($tx->status === 'success') {
            return response()->json(['status' => 'success', 'code' => (string) $tx->mpesa_receipt, 'amount' => (float) $tx->amount]);
        }

        if (in_array($tx->status, ['failed', 'cancelled'])) {
            return response()->json([
                'status' => $tx->status,
                'message' => $tx->status === 'cancelled' ? 'The customer cancelled the M-Pesa prompt.' : ($tx->result_desc ?: 'The M-Pesa payment failed.'),
            ]);
        }

        if ($age > 120) {
            return response()->json([
                'status' => 'timeout',
                'message' => 'No response from M-Pesa after 2 minutes. If the customer got an SMS, type the code below.',
            ]);
        }

        return response()->json(['status' => 'pending']);
    }

    /** Till/Paybill payments from the last 30 minutes that are not on any sale yet. */
    public function recent(SyncClient $sync)
    {
        // Branch server: till/paybill payments are reported to the cloud, so fetch new ones (at most every 5 s)
        if (SyncClient::enabled() && Cache::add('mpesa-recent-pull', true, 5)) {
            try {
                $sync->pull('mpesa_transactions', 2);
            } catch (Throwable $e) {
                // offline: show what we already have
            }
        }

        return response()->json(
            MpesaTransaction::where('type', 'c2b')->where('status', 'success')
                ->where('created_at', '>=', now()->subMinutes(30))
                ->whereDoesntHave('payments')
                ->latest()->limit(5)->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->first_name ?: 'Customer',
                    'code' => (string) $p->mpesa_receipt,
                    'amount' => (float) $p->amount,
                    'phone' => (string) $p->phone,
                    'time' => $p->created_at->format('H:i'),
                ])->values()
        );
    }
}
