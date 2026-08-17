<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cut_jobs', function (Blueprint $table): void {
            if (! Schema::hasColumn('cut_jobs', 'pipeline_step')) {
                $table->unsignedTinyInteger('pipeline_step')->default(0);
            }

            if (! Schema::hasColumn('cut_jobs', 'file_size_bytes')) {
                $table->unsignedBigInteger('file_size_bytes')->nullable();
            }

            if (! Schema::hasColumn('cut_jobs', 'error_detail')) {
                $table->text('error_detail')->nullable();
            }

            $table->index(['user_id', 'created_at'], 'cut_jobs_user_created_idx');
            $table->index(['user_id', 'status'], 'cut_jobs_user_status_idx');
            $table->index(['status', 'expires_at'], 'cut_jobs_status_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::table('cut_jobs', function (Blueprint $table): void {
            $table->dropIndex('cut_jobs_user_created_idx');
            $table->dropIndex('cut_jobs_user_status_idx');
            $table->dropIndex('cut_jobs_status_expires_idx');

            if (Schema::hasColumn('cut_jobs', 'pipeline_step')) {
                $table->dropColumn('pipeline_step');
            }
            if (Schema::hasColumn('cut_jobs', 'file_size_bytes')) {
                $table->dropColumn('file_size_bytes');
            }
            if (Schema::hasColumn('cut_jobs', 'error_detail')) {
                $table->dropColumn('error_detail');
            }
        });
    }
};
