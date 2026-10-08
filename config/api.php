<?php

return [
    'tokens' => [
        'prefix' => env('API_TOKEN_PREFIX', 'bl_live_'),
        'default_ttl_days' => (int) env('API_TOKEN_DEFAULT_TTL_DAYS', 365),
    ],

    'limits' => [
        'default_per_page' => (int) env('API_DEFAULT_PER_PAGE', 20),
        'max_per_page' => (int) env('API_MAX_PER_PAGE', 100),
        'requests_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 120),
        'stream_requests_per_minute' => (int) env('API_STREAM_RATE_LIMIT_PER_MINUTE', 20),
    ],

    'stream' => [
        'max_duration_seconds' => (int) env('API_STREAM_MAX_DURATION_SECONDS', 55),
        'max_client_timeout_seconds' => (int) env('API_STREAM_MAX_CLIENT_TIMEOUT_SECONDS', 60),
        'poll_interval_ms' => (int) env('API_STREAM_POLL_INTERVAL_MS', 1200),
        'heartbeat_seconds' => (int) env('API_STREAM_HEARTBEAT_SECONDS', 15),
        'batch_limit' => (int) env('API_STREAM_BATCH_LIMIT', 25),
        'max_concurrent_per_token' => (int) env('API_STREAM_MAX_CONCURRENT_PER_TOKEN', 2),
    ],
];
