<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Redundant: covered by notifications_notifiable_read_idx (leftmost-prefix rule).
        $this->tryDropIndex(fn () => Schema::table('notifications', fn (Blueprint $t) => $t->dropIndex('notifications_notifiable_type_notifiable_id_index')));

        // Redundant: covered by cut_jobs_status_expires_idx (leftmost-prefix rule).
        // The standalone (expires_at) index MUST stay — CleanupExpiredJobs filters
        // on expires_at with a non-equality predicate on status, which the composite
        // starting with status cannot serve efficiently.
        $this->tryDropIndex(fn () => Schema::table('cut_jobs', fn (Blueprint $t) => $t->dropIndex('cut_jobs_status_index')));
    }

    public function down(): void
    {
        $this->tryAddIndex(fn () => Schema::table('notifications', fn (Blueprint $t) => $t->index(['notifiable_type', 'notifiable_id'], 'notifications_notifiable_type_notifiable_id_index')));
        $this->tryAddIndex(fn () => Schema::table('cut_jobs', fn (Blueprint $t) => $t->index('status', 'cut_jobs_status_index')));
    }

    /**
     * try/catch must wrap the OUTER Schema::table (Blueprint queues commands
     * inside the closure and runs them on return, so inline try/catch inside
     * the closure never catches DDL errors — was a placebo).
     */
    private function tryAddIndex(callable $add): void
    {
        try {
            $add();
        } catch (Throwable $e) {
            // Index already exists — safe to skip.
        }
    }

    private function tryDropIndex(callable $drop): void
    {
        try {
            $drop();
        } catch (Throwable $e) {
            // Index already dropped — safe to skip.
        }
    }
};
