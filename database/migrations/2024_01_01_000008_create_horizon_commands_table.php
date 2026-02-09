<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horizon_commands', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('command');
            $table->jsonb('options')->default('{}');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['name', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horizon_commands');
    }
};