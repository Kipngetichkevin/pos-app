<?php

namespace App\Etims\Drivers;

use App\Etims\EtimsDriver;
use App\Etims\EtimsException;
use App\Models\Product;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Direct connection to KRA's OSCU API (TIS technical specification v2.0).
 * Needs KRA certification; test everything in the KRA sandbox before going live.
 */
class OscuDriver implements EtimsDriver
{
    public function registerItem(Product $product, string $itemCode): void
    {
        if (! $product->etims_class_code) {
            throw new EtimsException("Set the KRA item classification for {$product->name} on the Products screen.", retryable: false);
        }

        $this->post('/items/saveItems', [
            'tin' => config('etims.pin'),
            'bhfId' => config('etims.branch_id'),
            'itemCd' => $itemCode,
            'itemClsCd' => $product->etims_class_code,
            'itemTyCd' => '2',          // 2 = finished product
            'itemNm' => mb_substr($product->name, 0, 200),
            'orgnNatCd' => 'KE',
            'pkgUnitCd' => 'NT',
            'qtyUnitCd' => 'U',
            'taxTyCd' => $product->taxRate?->code ?? 'B',
            'dftPrc' => (float) $product->selling_price,
            'isrcAplcbYn' => 'N',
            'useYn' => 'Y',
            'regrId' => 'POS',
            'regrNm' => 'POS',
            'modrId' => 'POS',
            'modrNm' => 'POS',
        ]);
    }

    public function send(array $payload): array
    {
        $data = $this->post('/trnsSales/saveSales', $payload)->json('data', []);

        return [
            'receipt_no' => (string) ($data['curRcptNo'] ?? $data['rcptNo'] ?? ''),
            'total_receipt_no' => (string) ($data['totRcptNo'] ?? ''),
            'internal_data' => $data['intrlData'] ?? null,
            'signature' => $data['rcptSign'] ?? null,
            'sdc_id' => $data['sdcId'] ?? null,
            'mrc_no' => $data['mrcNo'] ?? null,
            'signed_at' => ! empty($data['sdcDateTime']) ? Carbon::createFromFormat('YmdHis', $data['sdcDateTime']) : now(),
        ];
    }

    protected function post(string $path, array $body): Response
    {
        if (! config('etims.pin') || ! config('etims.oscu.cmc_key')) {
            throw new EtimsException('eTIMS is not set up: ETIMS_PIN and ETIMS_CMC_KEY are missing in .env.', retryable: false);
        }

        try {
            $response = $this->http()->post($path, $body);
        } catch (ConnectionException $e) {
            throw new EtimsException('Cannot reach KRA eTIMS (no internet?). Will retry.');
        }

        if ($response->serverError()) {
            throw new EtimsException("KRA eTIMS error (HTTP {$response->status()}). Will retry.");
        }

        $code = (string) $response->json('resultCd');

        // 000 = OK. 001 = no data (also fine for some calls). Anything else is a rejection.
        if (! in_array($code, ['000', '001'], true)) {
            throw new EtimsException('KRA rejected it: ' . ($response->json('resultMsg') ?: "code {$code}"), retryable: false);
        }

        return $response;
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl(config('etims.oscu.url'))
            ->withHeaders([
                'tin' => config('etims.pin'),
                'bhfId' => config('etims.branch_id'),
                'cmcKey' => config('etims.oscu.cmc_key'),
            ])
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30);
    }
}
