<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_lifecycle_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_uuid')->nullable()->index();
            $table->string('gym_name');
            $table->string('slug');
            $table->string('owner_name')->nullable();
            $table->string('owner_email')->nullable();
            $table->string('action')->index();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_lifecycle_logs');
    }
};
