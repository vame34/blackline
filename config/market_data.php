<?php

return [
    'default_provider' => env('MARKET_DATA_DEFAULT_PROVIDER', 'bybit'),
    'fallback_providers' => ['binance'],
    'binance' => [
        'base_url' => env('BINANCE_API_BASE', 'https://api.binance.com'),
    ],
    'bybit' => [
        'base_url' => env('BYBIT_API_BASE', 'https://api.bybit.com'),
        'category' => env('BYBIT_CATEGORY', 'spot'),
    ],
];
