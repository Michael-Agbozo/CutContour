<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Columns: Schema::hasColumn guards inline (Schema::table can carry
        // several column adds in one build).
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
        });

        // Indexes: try/catch wraps the OUTER Schema::table so the DDL actually
        // executes inside the try. Blueprint queues commands and runs them
        // when the closure returns, so an inline try/catch inside the closure
        // never catches anything — a prior version's guards were placebos.
        $this->tryAddIndex(fn () => Schema::table('cut_jobs', fn (Blueprint $t) => $t->index(['user_id', 'created_at'], 'cut_jobs_user_created_idx')));
        $this->tryAddIndex(fn () => Schema::table('cut_jobs', fn (Blueprint $t) => $t->index(['user_id', 'status'], 'cut_jobs_user_status_idx')));
        $this->tryAddIndex(fn () => Schema::table('cut_jobs', fn (Blueprint $t) => $t->index(['status', 'expires_at'], 'cut_jobs_status_expires_idx')));
    }

    public function down(): void
    {
        $this->tryDropIndex(fn () => Schema::table('cut_jobs', fn (Blueprint $t) => $t->dropIndex('cut_jobs_user_created_idx')));
        $this->tryDropIndex(fn () => Schema::table('cut_jobs', fn (Blueprint $t) => $t->dropIndex('cut_jobs_user_status_idx')));
        $this->tryDropIndex(fn () => Schema::table('cut_jobs', fn (Blueprint $t) => $t->dropIndex('cut_jobs_status_expires_idx')));

        Schema::table('cut_jobs', function (Blueprint $table): void {
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
