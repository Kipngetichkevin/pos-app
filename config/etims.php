<?php

return [

    /*
    | Which eTIMS connection to use:
    |  - off:        eTIMS not used (not VAT/eTIMS registered). Sales are marked "not required".
    |  - fake:       pretend KRA accepted every invoice. For training and testing only.
    |  - oscu:       straight to KRA's OSCU API (needs KRA certification and a cmcKey per branch).
    |  - integrator: through a certified eTIMS integrator's API.
    */
    'driver' => env('ETIMS_DRIVER', 'off'),

    // The business KRA PIN (TIN) and this branch's eTIMS branch id (00 = head office)
    'pin' => env('ETIMS_PIN'),
    'branch_id' => env('ETIMS_BRANCH_ID', '00'),

    'oscu' => [
        // Sandbox: https://etims-api-sbx.kra.go.ke/etims-api  Live: https://etims-api.kra.go.ke/etims-api
        'url' => rtrim((string) env('ETIMS_OSCU_URL', 'https://etims-api-sbx.kra.go.ke/etims-api'), '/'),
        'device_serial' => env('ETIMS_DEVICE_SERIAL'),
        'cmc_key' => env('ETIMS_CMC_KEY'), // returned once by device initialisation
    ],

    'integrator' => [
        'url' => rtrim((string) env('ETIMS_INTEGRATOR_URL', ''), '/'),
        'key' => env('ETIMS_INTEGRATOR_KEY'),
    ],

    // Printed on receipts so customers can check the invoice with KRA
    'qr_base' => env('ETIMS_QR_BASE', 'https://etims.kra.go.ke/common/link/etims/receipt/indexEtimsReceiptData?Data='),

    // Retries for an invoice that fails (e.g. no internet) before a manager has to look at it
    'max_attempts' => (int) env('ETIMS_MAX_ATTEMPTS', 20),

];
