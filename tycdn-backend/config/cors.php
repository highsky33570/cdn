<?php

$allowedOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => trim($origin),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', (string) env('FRONTEND_URL', 'http://localhost:5173'))),
)));

// 仅在非生产环境下允许 localhost/127.0.0.1 的任意端口跨域。
// 生产环境必须通过 CORS_ALLOWED_ORIGINS 环境变量显式列出允许的来源。
$localhostPatterns = env('APP_ENV') === 'production'
    ? []
    : [
        '#^https?://localhost(?::\d+)?$#',
        '#^https?://127\.0\.0\.1(?::\d+)?$#',
    ];

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],
    'allowed_methods' => ['*'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => $localhostPatterns,
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
