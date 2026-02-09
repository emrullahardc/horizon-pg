<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horizon_metrics', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('type', 10);
            $table->bigInteger('throughput')->default(0);
            $table->decimal('runtime', 16, 6)->default(0);
            $table->timestamp('updated_at')->useCurrent();

            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horizon_metrics');
    }
};