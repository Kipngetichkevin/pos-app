<?php

// Accepts both naming styles: MPESA_ENV / MPESA_ENVIRONMENT, MPESA_PASSKEY / SAFARICOM_PASSKEY
$environment = strtolower((string) env('MPESA_ENV', env('MPESA_ENVIRONMENT', 'sandbox')));

return [
    'env' => in_array($environment, ['live', 'production', 'prod']) ? 'live' : 'sandbox',
    'consumer_key' => env('MPESA_CONSUMER_KEY'),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
    'shortcode' => env('MPESA_SHORTCODE', env('MPESA_BUSINESS_SHORTCODE')),
    'till_number' => env('MPESA_TILL_NUMBER'),
    'passkey' => env('MPESA_PASSKEY', env('SAFARICOM_PASSKEY')),
    'transaction_type' => env('MPESA_TRANSACTION_TYPE', 'CustomerPayBillOnline'),
    'stk_callback_url' => env('MPESA_STK_CALLBACK_URL'),
    'c2b_confirmation_url' => env('MPESA_C2B_CONFIRMATION_URL'),
    'c2b_validation_url' => env('MPESA_C2B_VALIDATION_URL'),
];
