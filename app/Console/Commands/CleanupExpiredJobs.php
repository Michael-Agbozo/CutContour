<?php

namespace App\Console\Commands;

use App\Models\CutJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

#[Signature('cutjob:cleanup')]
#[Description('Delete files for expired CutJobs and mark their records as expired (PRD §12).')]
class CleanupExpiredJobs extends Command
{
    public function handle(): int
    {
        $this->info('Starting expired job cleanup…');

        $deleted = 0;
        $failed = 0;

        CutJob::query()
            ->where('expires_at', '<', now())
            ->whereNot('status', 'expired')
            ->chunkById(100, function ($jobs) use (&$deleted, &$failed): void {
                foreach ($jobs as $job) {
                    try {
                        $this->purgeFiles($job);

                        $job->update([
                            'status' => 'expired',
                            'file_path' => null,
                            'output_path' => null,
                        ]);

                        $deleted++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::error('CleanupExpiredJobs: failed to purge job', [
                            'job_id' => $job->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('CleanupExpiredJobs: completed', [
            'deleted' => $deleted,
            'failed' => $failed,
        ]);

        $this->info("Cleanup complete. Deleted: {$deleted}, Failed: {$failed}");

        return self::SUCCESS;
    }

    private function purgeFiles(CutJob $job): void
    {
        $dir = "users/{$job->user_id}/jobs/{$job->id}";

        if (Storage::exists($dir)) {
            Storage::deleteDirectory($dir);
        }
    }
}
