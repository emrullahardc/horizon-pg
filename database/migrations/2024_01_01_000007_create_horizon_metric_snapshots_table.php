<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horizon_metric_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('key');
            $table->bigInteger('throughput')->default(0);
            $table->decimal('runtime', 16, 6)->default(0);
            $table->decimal('wait', 16, 6)->nullable();
            $table->integer('time');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['key', 'time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horizon_metric_snapshots');
    }
};
