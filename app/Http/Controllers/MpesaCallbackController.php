<?php

namespace App\Http\Controllers;

use App\Services\MpesaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class MpesaCallbackController extends Controller
{
    public function stk(Request $request, MpesaService $mpesa)
    {
        Log::info('M-Pesa STK callback', $request->all());

        $mpesa->handleStkCallback($request->all());

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    public function c2bValidation(Request $request)
    {
        Log::info('M-Pesa C2B validation', $request->all());

        return response()->json(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
    }

    public function c2bConfirmation(Request $request, MpesaService $mpesa)
    {
        Log::info('M-Pesa C2B confirmation', $request->all());

        try {
            $mpesa->handleC2bConfirmation($request->all());
        } catch (Throwable $e) {
            Log::error('C2B confirmation could not be saved: ' . $e->getMessage());
        }

        return response()->json(['ResultCode' => '0', 'ResultDesc' => 'Accepted']);
    }
}
