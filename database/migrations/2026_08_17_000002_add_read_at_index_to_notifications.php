<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            // Hot query path: notification bell polls WHERE notifiable_id = ? AND read_at IS NULL.
            // morphs() already covers (notifiable_type, notifiable_id) so extend it with read_at.
            try {
                $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_notifiable_read_idx');
            } catch (Throwable $e) {
                // index likely already exists
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            try {
                $table->dropIndex('notifications_notifiable_read_idx');
            } catch (Throwable $e) {
                // index likely already dropped
            }
        });
    }
};
