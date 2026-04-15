<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_report_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('email_thread_id')->constrained('email_threads')->cascadeOnDelete();
            $table->foreignId('ai_report_job_id')->constrained('ai_report_jobs')->cascadeOnDelete();
            $table->string('scope', 30)->default('overall');
            $table->json('snapshot_json')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('template_version', 50)->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('first_downloaded_at')->nullable();
            $table->timestamp('last_downloaded_at')->nullable();
            $table->foreignId('last_downloaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('ai_report_job_id');
            $table->index(['created_by', 'email_thread_id']);
            $table->index('last_downloaded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_report_versions');
    }
};
