<?php

return [
    'environment' => env('FEDAPAY_ENVIRONMENT', 'sandbox'),
    'public_key' => env('FEDAPAY_PUBLIC_KEY', ''),
    'secret_key' => env('FEDAPAY_SECRET_KEY', ''),
    'webhook_secret_payin' => env('FEDAPAY_WEBHOOK_SECRET', ''),
    'webhook_secret_payout' => env('FEDAPAY_WEBHOOK_SECRET_PAYOUT', env('FEDAPAY_WEBHOOK_SECRET', '')),
    'currency' => env('FEDAPAY_CURRENCY', 'XOF'),
    'country' => env('FEDAPAY_COUNTRY', 'bj'),

    'base_url' => env('FEDAPAY_ENVIRONMENT', 'sandbox') === 'live'
        ? 'https://api.fedapay.com'
        : 'https://sandbox-api.fedapay.com',
];
