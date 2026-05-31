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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('name');
            $table->string('version')->nullable();
            $table->enum('type', ['web', 'database', 'cache', 'queue', 'monitoring', 'other'])->default('other');
            $table->enum('status', ['running', 'stopped', 'failed', 'unknown'])->default('unknown');
            $table->string('package_manager')->nullable();
            $table->string('port')->nullable();
            $table->text('path')->nullable();
            $table->text('config_path')->nullable();
            $table->text('log_path')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_checked')->nullable();
            $table->timestamps();
            $table->unique(['server_id', 'name']);
            $table->index('status');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
