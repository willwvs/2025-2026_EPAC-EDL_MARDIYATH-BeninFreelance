<?php

return [
    'commission_rate' => (float) env('APP_COMMISSION_RATE', 0.05),
    'auto_release_days' => (int) env('APP_AUTO_RELEASE_DAYS', 14),
    'accept_deadline_hours' => (int) env('APP_ACCEPT_DEADLINE_HOURS', 48),
    // En sandbox : clôturer le litige en base si l'API payout FedaPay refuse (solde insuffisant, etc.)
    'dispute_allow_db_close' => filter_var(
        env('DISPUTE_ALLOW_DB_CLOSE', env('FEDAPAY_ENVIRONMENT', 'sandbox') === 'sandbox'),
        FILTER_VALIDATE_BOOL
    ),
];
