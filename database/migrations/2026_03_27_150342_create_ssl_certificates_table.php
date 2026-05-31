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
        Schema::create('ssl_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->unsignedSmallInteger('port')->default(443);
            $table->string('issuer')->nullable();
            $table->string('subject')->nullable();
            $table->json('sans')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->enum('status', ['valid', 'expiring_soon', 'expired', 'unknown'])->default('unknown');
            $table->timestamp('last_checked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ssl_certificates');
    }
};
