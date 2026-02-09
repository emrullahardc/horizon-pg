<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horizon_orphans', function (Blueprint $table) {
            $table->string('master');
            $table->string('process_id');
            $table->integer('observed_at');

            $table->primary(['master', 'process_id']);
            $table->index('master');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horizon_orphans');
    }
};