<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->float('cpu_usage_percent')->comment('0-100%');
            $table->float('memory_usage_percent')->comment('0-100%');
            $table->float('memory_used_gb');
            $table->float('memory_available_gb');
            $table->float('disk_usage_percent')->comment('0-100%');
            $table->float('disk_used_gb');
            $table->float('disk_available_gb');
            $table->float('load_average_1')->nullable();
            $table->float('load_average_5')->nullable();
            $table->float('load_average_15')->nullable();
            $table->integer('processes_running')->nullable();
            $table->integer('uptime_seconds')->nullable();
            $table->json('network_stats')->nullable();
            $table->json('additional_metrics')->nullable();
            $table->timestamps();
            $table->index('server_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_metrics');
    }
};
