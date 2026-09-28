<?php

return [
    'timezone' => env('NEWSFLOW_TIMEZONE', 'Asia/Bangkok'),
    'scan_times' => array_values(array_filter(array_map('trim', explode(',', env('NEWSFLOW_SCAN_TIMES', '08:00,14:00,20:00'))))),
    'stale_run_minutes' => (int) env('NEWSFLOW_STALE_RUN_MINUTES', 60),
    'source_alert_minutes' => (int) env('NEWSFLOW_SOURCE_ALERT_MINUTES', 720),
    'source_requests_per_minute' => (int) env('NEWSFLOW_SOURCE_REQUESTS_PER_MINUTE', 30),
    'max_source_bytes' => (int) env('NEWSFLOW_MAX_SOURCE_BYTES', 5242880),
    'max_source_text_chars' => (int) env('NEWSFLOW_MAX_SOURCE_TEXT_CHARS', 8000),
    'snapshot_retention_days' => (int) env('NEWSFLOW_SNAPSHOT_RETENTION_DAYS', 365),
];
