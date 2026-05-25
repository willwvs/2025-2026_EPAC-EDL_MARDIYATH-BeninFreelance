<?php

return [
    'commission_rate' => (float) env('APP_COMMISSION_RATE', 0.05),
    'auto_release_days' => (int) env('APP_AUTO_RELEASE_DAYS', 14),
    'accept_deadline_hours' => (int) env('APP_ACCEPT_DEADLINE_HOURS', 48),
];
