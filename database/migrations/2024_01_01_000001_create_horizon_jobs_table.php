<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horizon_jobs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('connection');
            $table->string('queue');
            $table->string('name');
            $table->string('status', 30)->default('pending');
            $table->text('payload');
            $table->text('exception')->nullable();
            $table->text('context')->nullable();
            $table->boolean('is_silenced')->default(false);
            $table->boolean('is_monitored')->default(false);
            $table->jsonb('retried_by')->nullable();
            $table->decimal('reserved_at', 16, 6)->nullable();
            $table->decimal('completed_at', 16, 6)->nullable();
            $table->decimal('failed_at', 16, 6)->nullable();
            $table->decimal('created_at', 16, 6);
            $table->decimal('updated_at', 16, 6);
            $table->timestamp('expires_at')->nullable();

            $table->index('status');
            $table->index(['created_at']);
        });

        // Partial indexes for efficient querying
        DB::statement('CREATE INDEX idx_hj_pending ON horizon_jobs (created_at DESC) WHERE status = \'pending\'');
        DB::statement('CREATE INDEX idx_hj_completed ON horizon_jobs (completed_at DESC) WHERE status = \'completed\' AND is_silenced = false');
        DB::statement('CREATE INDEX idx_hj_silenced ON horizon_jobs (completed_at DESC) WHERE status = \'completed\' AND is_silenced = true');
        DB::statement('CREATE INDEX idx_hj_failed ON horizon_jobs (failed_at DESC) WHERE status = \'failed\'');
        DB::statement('CREATE INDEX idx_hj_monitored ON horizon_jobs (created_at DESC) WHERE is_monitored = true');
        DB::statement('CREATE INDEX idx_hj_expires ON horizon_jobs (expires_at) WHERE expires_at IS NOT NULL');

        // Job ID sequence
        DB::statement('CREATE SEQUENCE IF NOT EXISTS horizon_job_id_seq');
    }

    public function down(): void
    {
        Schema::dropIfExists('horizon_jobs');
        DB::statement('DROP SEQUENCE IF EXISTS horizon_job_id_seq');
    }
};