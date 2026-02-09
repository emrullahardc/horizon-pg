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
            DB::statement('CREATE UNLOGGED TABLE horizon_tags (
                id BIGSERIAL PRIMARY KEY,
                job_id VARCHAR(255) NOT NULL,
                tag VARCHAR(255) NOT NULL,
                score DECIMAL(16, 6) NOT NULL,
                expires_at TIMESTAMP
            ) WITH (FILLFACTOR = 70)');

            DB::statement('CREATE INDEX idx_ht_tag_score ON horizon_tags (tag, score DESC)');
            DB::statement('CREATE INDEX idx_ht_job_id ON horizon_tags (job_id)');
            DB::statement('CREATE UNIQUE INDEX idx_ht_unique ON horizon_tags (tag, job_id)');
        } else {
            Schema::create('horizon_tags', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('job_id');
                $table->string('tag');
                $table->decimal('score', 16, 6);
                $table->timestamp('expires_at')->nullable();

                $table->index('job_id');
                $table->index(['tag', 'score']);
                $table->unique(['tag', 'job_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('horizon_tags');
    }
};