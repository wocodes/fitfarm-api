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
        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('uuid')->unique();
            $table->string('name');
            $table->string('token_hash'); // SHA-256 hash of device token
            $table->string('device_fingerprint')->nullable();
            $table->unsignedBigInteger('registered_by')->nullable();
            $table->string('last_ip_address')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('terminals');
    }
};
