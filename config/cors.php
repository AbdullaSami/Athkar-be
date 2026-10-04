<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(
        array_map(trim(...), explode(',', (string) env('DASHBOARD_ORIGINS', ''))),
        fn (string $origin): bool => preg_match('/^https?:\/\/[^\s\/]+$/D', $origin) === 1 && ! str_contains($origin, '*'),
    )),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-CSRF-TOKEN', 'X-XSRF-TOKEN', 'X-Requested-With'],
    'exposed_headers' => ['Retry-After'],
    'max_age' => 600,
    'supports_credentials' => true,
];
