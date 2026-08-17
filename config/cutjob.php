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
    | Output DPI (Resolution)
    |--------------------------------------------------------------------------
    |
    | The DPI used for pixel ↔ physical-unit conversions and PDF output.
    | 300 is standard for print-quality output.
    |
    */
    'dpi' => (int) env('CUTJOB_DPI', 300),

    /*
    |--------------------------------------------------------------------------
    | Monthly Job Limit (Free Tier)
    |--------------------------------------------------------------------------
    |
    | Maximum number of non-failed jobs a free-tier user may create per
    | calendar month. Admins can reset a user's counter from the admin panel.
    |
    */
    'monthly_job_limit' => (int) env('CUTJOB_MONTHLY_JOB_LIMIT', 10),

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
        'qpdf' => env('QPDF_BINARY', 'qpdf'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cut Path Spot Color
    |--------------------------------------------------------------------------
    |
    | Print RIPs interpret a named spot color separation as a cut path. Default
    | is "CutContour" (Roland VersaWorks convention) with CMYK 0/100/0/0 as the
    | on-screen fallback. Override per print house — Mimaki uses "CutContour1",
    | Cricut uses "CutMark", etc.
    |
    */
    'spot_color' => [
        'name' => env('CUTJOB_SPOT_COLOR_NAME', 'CutContour'),
        'cmyk' => [
            (float) env('CUTJOB_SPOT_COLOR_C', 0),
            (float) env('CUTJOB_SPOT_COLOR_M', 100),
            (float) env('CUTJOB_SPOT_COLOR_Y', 0),
            (float) env('CUTJOB_SPOT_COLOR_K', 0),
        ],
        'hex_fallback' => env('CUTJOB_SPOT_COLOR_HEX', '#ec008c'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cut Path Line Width
    |--------------------------------------------------------------------------
    |
    | Stroke width for the cut path, in points. 0.25pt is the standard hairline
    | used by most cutter drivers.
    |
    */
    'cut_line_width_pt' => (float) env('CUTJOB_CUT_LINE_WIDTH_PT', 0.25),

];
