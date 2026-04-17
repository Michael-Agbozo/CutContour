<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cut_jobs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('file_path')->nullable();
            $table->string('output_path')->nullable();
            $table->string('file_type', 10);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->enum('status', ['processing', 'completed', 'failed', 'expired'])->default('processing')->index();
            $table->boolean('ai_used')->default(false);
            $table->float('confidence_score')->nullable();
            $table->unsignedInteger('processing_duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cut_jobs');
    }
};
