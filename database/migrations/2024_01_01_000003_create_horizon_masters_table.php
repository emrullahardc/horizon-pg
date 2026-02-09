<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horizon_masters', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->string('environment')->nullable();
            $table->integer('pid');
            $table->string('status', 30)->default('running');
            $table->jsonb('supervisors')->default('[]');
            $table->timestamp('heartbeat')->useCurrent();
            $table->timestamp('expires_at');

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horizon_masters');
    }
};