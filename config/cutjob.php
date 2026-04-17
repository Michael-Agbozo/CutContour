<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CutJob Retention
    |--------------------------------------------------------------------------
    |
    | Number of days to retain job files before the cleanup command purges them.
    | Set via CUTJOB_RETENTION_DAYS in your .env (PRD §12).
    |
    */
    'retention_days' => (int) env('CUTJOB_RETENTION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Max Upload Size
    |--------------------------------------------------------------------------
    |
    | Maximum allowed file size in megabytes (PRD §6).
    |
    */
    'max_file_size_mb' => (int) env('CUTJOB_MAX_FILE_SIZE_MB', 100),

    /*
    |--------------------------------------------------------------------------
    | Confidence Threshold
    |--------------------------------------------------------------------------
    |
    | Jobs with a confidence score below this value are routed to the
    | AI-Enhanced path. Range: 0.0–1.0 (PRD §7).
    |
    */
    'confidence_threshold' => (float) env('CUTJOB_CONFIDENCE_THRESHOLD', 0.65),

];
