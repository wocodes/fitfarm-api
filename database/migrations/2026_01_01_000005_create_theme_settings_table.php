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
        Schema::create('theme_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('primary_color')->default('#fa6a2e');
            $table->string('accent_color')->default('#1e293b');
            $table->string('bg_color')->default('#0f0f11');
            $table->string('text_color')->default('#ffffff');
            $table->string('font_family')->default('Inter');
            $table->boolean('dark_mode')->default(true);
            $table->string('logo_url')->nullable();
            $table->string('favicon_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('theme_settings');
    }
};
