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
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('hostname');
            $table->string('domain');
            $table->string('ip_address')->unique();
            $table->enum('status', ['online', 'offline', 'maintenance', 'error'])->default('offline');
            $table->enum('os', ['oracle'])->default('oracle');
            $table->enum('os_version', ['7', '8', '9', '10'])->default('8');
            $table->integer('cpu_cores')->default(1);
            $table->string('cpu_model')->nullable();
            $table->integer('ram_gb')->default(1);
            $table->string('ssh_port')->default('22');
            $table->string('ssh_user')->nullable();
            $table->string('environment')->default('production');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_health_check')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('environment');
            $table->index('domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
