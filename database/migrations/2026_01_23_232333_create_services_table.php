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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('name');
            $table->string('service_name')->comment('systemd/service name');
            $table->enum('status', ['running', 'stopped', 'failed', 'unknown'])->default('unknown');
            $table->enum('enabled', ['enabled', 'disabled', 'unknown'])->default('unknown');
            $table->text('description')->nullable();
            $table->text('path')->nullable();
            $table->integer('port')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_checked')->nullable();
            $table->timestamps();
            $table->unique(['server_id', 'service_name']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
