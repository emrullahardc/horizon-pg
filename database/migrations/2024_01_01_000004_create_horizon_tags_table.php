<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horizon_tags', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('job_id');
            $table->string('tag');
            $table->decimal('score', 16, 6);
            $table->timestamp('expires_at')->nullable();

            $table->index('job_id');
        });

        DB::statement('CREATE UNIQUE INDEX idx_ht_unique ON horizon_tags (tag, job_id)');
        DB::statement('CREATE INDEX idx_ht_tag_score ON horizon_tags (tag, score DESC)');
        DB::statement('CREATE INDEX idx_ht_expires ON horizon_tags (expires_at) WHERE expires_at IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('horizon_tags');
    }
};