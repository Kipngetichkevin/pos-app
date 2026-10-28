<?php

namespace App\Etims\Drivers;

use App\Etims\EtimsDriver;
use App\Etims\EtimsException;
use App\Models\Product;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Sends invoices through a certified eTIMS integrator.
 * Most integrators accept KRA's own OSCU payload and return KRA's fields; adjust the two
 * endpoint paths and the response mapping below to match the integrator you sign up with.
 */
class IntegratorDriver implements EtimsDriver
{
    public function registerItem(Product $product, string $itemCode): void
    {
        $this->post('/items', [
            'itemCd' => $itemCode,
            'itemClsCd' => $product->etims_class_code,
            'itemNm' => $product->name,
            'taxTyCd' => $product->taxRate?->code ?? 'B',
            'dftPrc' => (float) $product->selling_price,
        ]);
    }

    public function send(array $payload): array
    {
        $data = $this->post('/sales', $payload);

        return [
            'receipt_no' => (string) ($data['curRcptNo'] ?? $data['rcptNo'] ?? $data['receipt_no'] ?? ''),
            'total_receipt_no' => (string) ($data['totRcptNo'] ?? ''),
            'internal_data' => $data['intrlData'] ?? $data['internal_data'] ?? null,
            'signature' => $data['rcptSign'] ?? $data['signature'] ?? null,
            'sdc_id' => $data['sdcId'] ?? null,
            'mrc_no' => $data['mrcNo'] ?? null,
            'signed_at' => ! empty($data['sdcDateTime']) ? Carbon::createFromFormat('YmdHis', $data['sdcDateTime']) : now(),
        ];
    }

    protected function post(string $path, array $body): array
    {
        if (! config('etims.integrator.url') || ! config('etims.integrator.key')) {
            throw new EtimsException('eTIMS integrator is not set up: ETIMS_INTEGRATOR_URL and ETIMS_INTEGRATOR_KEY are missing in .env.', retryable: false);
        }

        try {
            $response = Http::baseUrl(config('etims.integrator.url'))
                ->withToken(config('etims.integrator.key'))
                ->acceptJson()->connectTimeout(10)->timeout(30)
                ->post($path, $body);
        } catch (ConnectionException $e) {
            throw new EtimsException('Cannot reach the eTIMS integrator (no internet?). Will retry.');
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new EtimsException("eTIMS integrator error (HTTP {$response->status()}). Will retry.");
        }

        if (! $response->successful()) {
            throw new EtimsException('eTIMS integrator rejected it: ' . ($response->json('message') ?? $response->json('resultMsg') ?? $response->body()), retryable: false);
        }

        return $response->json('data') ?? $response->json() ?? [];
    }
}
