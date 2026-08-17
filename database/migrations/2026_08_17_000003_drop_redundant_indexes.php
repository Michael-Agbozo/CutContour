<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Redundant: covered by notifications_notifiable_read_idx (leftmost-prefix rule).
        Schema::table('notifications', function (Blueprint $table): void {
            try {
                $table->dropIndex('notifications_notifiable_type_notifiable_id_index');
            } catch (Throwable $e) {
                // index likely already dropped
            }
        });

        // Redundant: covered by cut_jobs_status_expires_idx (leftmost-prefix rule).
        // The standalone (expires_at) index MUST stay — CleanupExpiredJobs filters
        // on expires_at with a non-equality predicate on status, which the composite
        // starting with status cannot serve efficiently.
        Schema::table('cut_jobs', function (Blueprint $table): void {
            try {
                $table->dropIndex('cut_jobs_status_index');
            } catch (Throwable $e) {
                // index likely already dropped
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            try {
                $table->index(['notifiable_type', 'notifiable_id'], 'notifications_notifiable_type_notifiable_id_index');
            } catch (Throwable $e) {
                // index likely already exists
            }
        });

        Schema::table('cut_jobs', function (Blueprint $table): void {
            try {
                $table->index('status', 'cut_jobs_status_index');
            } catch (Throwable $e) {
                // index likely already exists
            }
        });
    }
};
