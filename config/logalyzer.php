<?php

return [
    'url' => env('LOGALYZER_URL', ''),
    'token' => env('LOGALYZER_TOKEN', ''),
    'log_name' => env('LOGALYZER_LOG_NAME', 'Puzzle Garden — Server'),
    'environment' => env('APP_ENV', 'production'),
    'log_type' => 'laravel',
    'project_id' => env('LOGALYZER_PROJECT_ID') ? (int) env('LOGALYZER_PROJECT_ID') : null,
    'level' => env('LOGALYZER_LEVEL', 'warning'),
    // Keep a logging outage from tying up this app's five PHP-FPM workers.
    'timeout' => (float) env('LOGALYZER_TIMEOUT', 1),
];
