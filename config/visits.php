<?php

return [
    'connection' => env('ANALYTICS_DB_CONNECTION', 'analytics'),
    'enabled' => (bool) env('VISITS_ENABLED', true),
    'session_minutes' => (int) env('VISITS_SESSION_MINUTES', 30),
    'geo' => [
        'driver' => env('VISITS_GEO_DRIVER', 'ipwho'), // ipwho | none
        'endpoint' => env('VISITS_GEO_ENDPOINT', 'https://ipwho.is'),
        'timeout' => (int) env('VISITS_GEO_TIMEOUT', 5),
        'daily_budget' => (int) env('VISITS_GEO_DAILY_BUDGET', 900),
        'batch' => (int) env('VISITS_GEO_BATCH', 5),
    ],
];
