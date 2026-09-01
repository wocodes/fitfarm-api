<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_locations', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->default(0);
            $table->decimal('longitude', 10, 7)->default(0);
            $table->decimal('radius', 8, 2)->default(50);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_locations');
    }
};
