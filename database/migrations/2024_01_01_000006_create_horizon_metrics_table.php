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
            DB::statement('CREATE UNLOGGED TABLE horizon_metrics (
                key VARCHAR(255) PRIMARY KEY,
                type VARCHAR(10) NOT NULL,
                throughput BIGINT DEFAULT 0,
                runtime DECIMAL(16, 6) DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) WITH (FILLFACTOR = 70)');
        } else {
            Schema::create('horizon_metrics', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('type');
                $table->bigInteger('throughput')->default(0);
                $table->decimal('runtime', 16, 6)->default(0);
                $table->timestamp('updated_at')->useCurrent();

                $table->index('type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('horizon_metrics');
    }
};