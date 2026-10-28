<?php

namespace App\Jobs;

use App\Models\EtimsInvoice;
use App\Services\EtimsService;
use Illuminate\Foundation\Bus\Dispatchable;

/** Sends one invoice to KRA right after the cashier's page has loaded. Failures are retried by `etims:send`. */
class SendEtimsInvoice
{
    use Dispatchable;

    public function __construct(public string $invoiceId)
    {
    }

    public function handle(EtimsService $etims): void
    {
        if ($invoice = EtimsInvoice::find($this->invoiceId)) {
            $etims->send($invoice);
        }
    }
}
