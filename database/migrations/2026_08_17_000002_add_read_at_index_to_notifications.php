<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // try/catch must wrap the OUTER Schema::table so it catches DDL errors
        // from Blueprint::build (inline try/catch inside the closure is a placebo).
        try {
            Schema::table('notifications', function (Blueprint $table): void {
                // Hot query path: notification bell polls WHERE notifiable_id = ? AND read_at IS NULL.
                // morphs() already covers (notifiable_type, notifiable_id) so extend it with read_at.
                $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_idx');
            });
        } catch (Throwable $e) {
            // Index already exists — safe to skip.
        }
    }

    public function down(): void
    {
        try {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->dropIndex('notifications_notifiable_read_idx');
            });
        } catch (Throwable $e) {
            // Index already dropped — safe to skip.
        }
    }
};
