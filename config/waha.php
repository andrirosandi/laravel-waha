<?php

return [
    'API_HOST' => env('WAHA_API_HOST', 'https://api.waha.io'),
    'API_KEY' => env('WAHA_API_KEY', 'your_api_key'),
    'BASIC_AUTH_USER' => env('WAHA_BASIC_AUTH_USER', 'your_basic_auth_user'),
    'BASIC_AUTH_PASSWORD' => env('WAHA_BASIC_AUTH_PASSWORD', 'your_basic_auth_password'),

    // Tambahkan konfigurasi webhook endpoint
    'WEBHOOK_ENDPOINT' => env('WAHA_WEBHOOK_ENDPOINT', 'https://yourdomain.com/waha-webhook'),

    // Konfigurasi rate limit
    'RATE_LIMIT' => [
        'MAX_ATTEMPTS' => env('WAHA_RATE_LIMIT_MAX_ATTEMPTS', 5),  // Maksimal pesan per menit
        'DECAY_MINUTES' => env('WAHA_RATE_LIMIT_DECAY_MINUTES', 1),  // Waktu reset limit (menit)
        'MAX_WAIT_TIME' => env('WAHA_RATE_LIMIT_MAX_WAIT_TIME', 300),  // SECOND
    ],
];
