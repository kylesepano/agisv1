<?php

return [
    'read_rate_limit' => (int) env('AIS_READ_RATE_LIMIT', 120),
    'generate_rate_limit' => (int) env('AIS_GENERATE_RATE_LIMIT', 12),
    'export_rate_limit' => (int) env('AIS_EXPORT_RATE_LIMIT', 20),
    'read_cache_seconds' => (int) env('AIS_READ_CACHE_SECONDS', 30),
    'hardening_version' => 'AIS-4.0',
];
