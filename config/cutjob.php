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
    | Failed Job Retention
    |--------------------------------------------------------------------------
    |
    | Number of hours to retain failed jobs before the cleanup command purges
    | them. Failed jobs are cleaned up more aggressively than successful ones.
    |
    */
    'failed_retention_hours' => (int) env('CUTJOB_FAILED_RETENTION_HOURS', 3),

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

    /*
    |--------------------------------------------------------------------------
    | Max Target Dimensions
    |--------------------------------------------------------------------------
    |
    | Maximum target dimension in centimetres. Both width and height are
    | validated against this limit (converted from the user's selected unit).
    |
    */
    'max_dimension_cm' => (float) env('CUTJOB_MAX_DIMENSION_CM', 300),

    /*
    |--------------------------------------------------------------------------
    | External Binary Paths
    |--------------------------------------------------------------------------
    |
    | Absolute paths to the CLI tools used by the pipeline. Override these when
    | the binaries are not on PHP's restricted exec() PATH (e.g. Herd on macOS).
    |
    */
    'binaries' => [
        'convert' => env('IMAGEMAGICK_BINARY', 'convert'),
        'identify' => env('IMAGEMAGICK_IDENTIFY_BINARY', 'identify'),
        'potrace' => env('POTRACE_BINARY', 'potrace'),
        'inkscape' => env('INKSCAPE_BINARY', 'inkscape'),
        'gs' => env('GHOSTSCRIPT_BINARY', 'gs'),
    ],

];
