<?php

return [
    'timezone' => env('NEWSFLOW_TIMEZONE', 'Asia/Bangkok'),
    'scan_times' => array_values(array_filter(array_map('trim', explode(',', env('NEWSFLOW_SCAN_TIMES', '08:00,14:00,20:00'))))),
    'stale_run_minutes' => (int) env('NEWSFLOW_STALE_RUN_MINUTES', 60),
    'source_alert_minutes' => (int) env('NEWSFLOW_SOURCE_ALERT_MINUTES', 720),
];
