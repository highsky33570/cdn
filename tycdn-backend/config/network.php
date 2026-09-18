<?php

return [
    // Public edge inventory supplied by the operator. IPs stay server-side.
    // IP geolocation checked with ipwho.is on 2026-09-18: all five are Hong Kong.
    'targets' => [
        'hk-01' => ['ip' => '156.234.124.162', 'location' => 'hongkong'],
        'hk-02' => ['ip' => '156.234.43.114', 'location' => 'hongkong'],
        'hk-03' => ['ip' => '156.234.2.74', 'location' => 'hongkong'],
        'hk-04' => ['ip' => '156.234.79.50', 'location' => 'hongkong'],
        'hk-05' => ['ip' => '156.234.199.130', 'location' => 'hongkong'],
    ],
    'locations' => [
        'hongkong' => ['label' => '香港', 'x' => 80.5, 'y' => 63],
    ],
    // 5 targets × 3 probes × 4 rounds/hour = 60 tests/hour; no paid token used.
    'monitoring_enabled' => env('NETWORK_MONITORING_ENABLED', true),
    'probe_limit' => 3,
    'interval_minutes' => 15,
    'fresh_minutes' => 35,
];
