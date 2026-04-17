<?php

use App\Console\Commands\CleanupExpiredJobs;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Delete files and mark records expired for jobs past their 90-day retention (PRD §12)
Schedule::command(CleanupExpiredJobs::class)
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/cleanup.log'));
