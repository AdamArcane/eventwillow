<?php
return [
    'site_key' => env('EW_SITE_API_KEY', ''),
    'enforce_ip_allowlist' => env('EW_SITE_API_ENFORCE_IP_ALLOWLIST', true),
    'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('EW_SITE_API_ALLOWED_IPS', ''))))),
    'trusted_proxy_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('EW_SITE_API_TRUSTED_PROXIES', ''))))),
    'requests_per_minute' => 120,
    'max_range_days' => 365,
    'max_per_page' => 100,
    'test_content_pattern' => '(^|[[:space:]_-])(demo|test|testing|dev|development|sandbox)([[:space:]_-]|$)',
];
