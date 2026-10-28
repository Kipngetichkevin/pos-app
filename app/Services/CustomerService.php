<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    /**
     * Record a change in what a customer owes.
     * Positive amount = they owe more (credit sale). Negative = they owe less (payment, refund).
     */
    public static function record(Customer $customer, string $type, float $amount, array $extra = []): CustomerTransaction
    {
        return DB::transaction(function () use ($customer, $type, $amount, $extra) {
            $locked = Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $locked->balance = round((float) $locked->balance + $amount, 2);
            $locked->save();

            return CustomerTransaction::create(array_merge([
                'customer_id' => $locked->id,
                'type' => $type,
                'amount' => round($amount, 2),
                'balance_after' => $locked->balance,
                'user_id' => auth()->id(),
            ], $extra));
        });
    }
}
