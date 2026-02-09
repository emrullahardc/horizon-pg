<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE UNLOGGED TABLE horizon_jobs (
                id VARCHAR(255) PRIMARY KEY,
                connection VARCHAR(255) NOT NULL,
                queue VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                status VARCHAR(30) DEFAULT \'pending\',
                payload TEXT NOT NULL,
                exception TEXT,
                context TEXT,
                is_silenced BOOLEAN DEFAULT FALSE,
                is_monitored BOOLEAN DEFAULT FALSE,
                retried_by JSONB,
                reserved_at DECIMAL(16, 6),
                completed_at DECIMAL(16, 6),
                failed_at DECIMAL(16, 6),
                created_at DECIMAL(16, 6) NOT NULL,
                updated_at DECIMAL(16, 6) NOT NULL,
                expires_at TIMESTAMP
            ) WITH (FILLFACTOR = 70)');

            DB::statement('CREATE INDEX idx_hj_status_created ON horizon_jobs (status, created_at DESC)');
            DB::statement('CREATE INDEX idx_hj_monitored ON horizon_jobs (created_at DESC) WHERE is_monitored = true');
            DB::statement('CREATE INDEX idx_hj_expires ON horizon_jobs (expires_at) WHERE expires_at IS NOT NULL');
            DB::statement('CREATE SEQUENCE IF NOT EXISTS horizon_job_id_seq');
        } else {
            Schema::create('horizon_jobs', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('connection');
                $table->string('queue');
                $table->string('name');
                $table->string('status')->default('pending');
                $table->text('payload');
                $table->text('exception')->nullable();
                $table->text('context')->nullable();
                $table->boolean('is_silenced')->default(false);
                $table->boolean('is_monitored')->default(false);
                $table->json('retried_by')->nullable();
                $table->decimal('reserved_at', 16, 6)->nullable();
                $table->decimal('completed_at', 16, 6)->nullable();
                $table->decimal('failed_at', 16, 6)->nullable();
                $table->decimal('created_at', 16, 6);
                $table->decimal('updated_at', 16, 6);
                $table->timestamp('expires_at')->nullable();

                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('horizon_jobs');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP SEQUENCE IF EXISTS horizon_job_id_seq');
        }
    }
};