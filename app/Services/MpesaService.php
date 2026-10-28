<?php

namespace App\Services;

use App\Models\MpesaTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MpesaService
{
    protected function baseUrl(): string
    {
        return config('mpesa.env') === 'live'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    protected function tokenCacheKey(): string
    {
        return 'mpesa_access_token_' . config('mpesa.env');
    }

    public function accessToken(): string
    {
        return Cache::remember($this->tokenCacheKey(), now()->addMinutes(50), function () {
            $response = Http::withBasicAuth(config('mpesa.consumer_key'), config('mpesa.consumer_secret'))
                ->connectTimeout(10)->timeout(20)
                ->get($this->baseUrl() . '/oauth/v1/generate', ['grant_type' => 'client_credentials']);

            if (! $response->successful() || ! $response->json('access_token')) {
                Log::error('M-Pesa token error', ['status' => $response->status(), 'body' => $response->body()]);
                throw new RuntimeException('Could not connect to M-Pesa. Check the internet connection and M-Pesa settings.');
            }

            return $response->json('access_token');
        });
    }

    /** Convert 0712345678 / +254712345678 / 712345678 into 254712345678 */
    public static function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '254' . substr($digits, 1);
        } elseif (strlen($digits) === 9 && in_array($digits[0], ['7', '1'])) {
            $digits = '254' . $digits;
        }

        return preg_match('/^254[17]\d{8}$/', $digits) ? $digits : null;
    }

    protected function password(string $timestamp): string
    {
        return base64_encode(config('mpesa.shortcode') . config('mpesa.passkey') . $timestamp);
    }

    public function stkPush(string $phone, float $amount, string $branchId, ?string $tillId, string $reference = 'POS'): MpesaTransaction
    {
        $phone = self::normalizePhone($phone);

        if (! $phone) {
            throw new RuntimeException('Enter a valid Safaricom number, e.g. 0712345678.');
        }

        $amount = (int) ceil($amount);

        if ($amount < 1) {
            throw new RuntimeException('Amount must be at least KSh 1.');
        }

        $timestamp = now()->format('YmdHis');

        $response = Http::withToken($this->accessToken())
            ->connectTimeout(10)->timeout(20)
            ->post($this->baseUrl() . '/mpesa/stkpush/v1/processrequest', [
                'BusinessShortCode' => config('mpesa.shortcode'),
                'Password' => $this->password($timestamp),
                'Timestamp' => $timestamp,
                'TransactionType' => config('mpesa.transaction_type'),
                'Amount' => $amount,
                'PartyA' => $phone,
                'PartyB' => config('mpesa.till_number') ?: config('mpesa.shortcode'),
                'PhoneNumber' => $phone,
                'CallBackURL' => config('mpesa.stk_callback_url'),
                'AccountReference' => substr($reference, 0, 12),
                'TransactionDesc' => 'POS payment',
            ]);

        if (! $response->successful()) {
            Cache::forget($this->tokenCacheKey());
        }

        if (! $response->successful() || (string) $response->json('ResponseCode') !== '0') {
            Log::error('M-Pesa STK error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new RuntimeException($response->json('errorMessage') ?? $response->json('CustomerMessage') ?? 'M-Pesa request failed. Try again.');
        }

        return MpesaTransaction::create([
            'branch_id' => $branchId,
            'till_id' => $tillId,
            'type' => 'stk_push',
            'merchant_request_id' => $response->json('MerchantRequestID'),
            'checkout_request_id' => $response->json('CheckoutRequestID'),
            'phone' => $phone,
            'amount' => $amount,
            'status' => 'pending',
        ]);
    }

    public function queryStatus(MpesaTransaction $tx): MpesaTransaction
    {
        if ($tx->status !== 'pending' || ! $tx->checkout_request_id) {
            return $tx;
        }

        $timestamp = now()->format('YmdHis');

        $response = Http::withToken($this->accessToken())
            ->connectTimeout(10)->timeout(20)
            ->post($this->baseUrl() . '/mpesa/stkpushquery/v1/query', [
                'BusinessShortCode' => config('mpesa.shortcode'),
                'Password' => $this->password($timestamp),
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $tx->checkout_request_id,
            ]);

        $code = $response->json('ResultCode');

        if ($code !== null && (string) $code !== '0') {
            $tx->update([
                'status' => (string) $code === '1032' ? 'cancelled' : 'failed',
                'result_code' => (string) $code,
                'result_desc' => $response->json('ResultDesc'),
            ]);
        }

        return $tx->fresh();
    }

    public function handleStkCallback(array $payload): void
    {
        $callback = data_get($payload, 'Body.stkCallback');

        if (! $callback) {
            return;
        }

        $tx = MpesaTransaction::where('checkout_request_id', $callback['CheckoutRequestID'] ?? null)->first();

        if (! $tx) {
            Log::warning('M-Pesa callback for unknown request', $payload);
            return;
        }

        $items = collect(data_get($callback, 'CallbackMetadata.Item', []))->pluck('Value', 'Name');
        $code = (string) ($callback['ResultCode'] ?? '');
        $date = $items->get('TransactionDate');

        $tx->update([
            'status' => $code === '0' ? 'success' : ($code === '1032' ? 'cancelled' : 'failed'),
            'result_code' => $code,
            'result_desc' => $callback['ResultDesc'] ?? null,
            'mpesa_receipt' => $items->get('MpesaReceiptNumber'),
            'amount' => $items->get('Amount') ?? $tx->amount,
            'transacted_at' => $date ? Carbon::createFromFormat('YmdHis', (string) $date) : null,
            'raw_callback' => $payload,
        ]);
    }

    /** Save a payment Safaricom reports to our paybill (customer paid from their phone) */
    public function handleC2bConfirmation(array $payload): ?MpesaTransaction
    {
        $receipt = $payload['TransID'] ?? null;

        if (! $receipt) {
            return null;
        }

        $tx = MpesaTransaction::firstOrNew(['mpesa_receipt' => $receipt]);

        // Already recorded (e.g. it came through STK push)
        if ($tx->exists && $tx->status === 'success') {
            return $tx;
        }

        $phone = (string) ($payload['MSISDN'] ?? '');
        $time = $payload['TransTime'] ?? null;

        $tx->fill([
            'type' => 'c2b',
            'phone' => strlen($phone) <= 20 ? $phone : null, // Safaricom may send a masked/hashed number
            'first_name' => trim((string) ($payload['FirstName'] ?? '')) ?: null,
            'bill_ref' => substr((string) ($payload['BillRefNumber'] ?? ''), 0, 50) ?: null,
            'amount' => (float) ($payload['TransAmount'] ?? 0),
            'status' => 'success',
            'result_code' => '0',
            'result_desc' => 'C2B confirmation',
            'transacted_at' => $time ? Carbon::createFromFormat('YmdHis', (string) $time) : now(),
            'raw_callback' => $payload,
        ])->save();

        return $tx;
    }

    public function registerC2bUrls(): array
    {
        $payload = [
            'ShortCode' => config('mpesa.shortcode'),
            'ResponseType' => 'Completed',
            'ConfirmationURL' => config('mpesa.c2b_confirmation_url'),
            'ValidationURL' => config('mpesa.c2b_validation_url'),
        ];

        $result = [];

        foreach (['/mpesa/c2b/v2/registerurl', '/mpesa/c2b/v1/registerurl'] as $path) {
            $response = Http::withToken($this->accessToken())
                ->connectTimeout(10)->timeout(30)
                ->post($this->baseUrl() . $path, $payload);

            $result = ['endpoint' => $path, 'status' => $response->status(), 'body' => $response->json() ?? $response->body()];

            if ($response->successful()) {
                break;
            }
        }

        return $result;
    }
}
