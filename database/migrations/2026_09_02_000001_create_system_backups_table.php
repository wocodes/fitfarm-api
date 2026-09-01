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
        Schema::create('system_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->onDelete('cascade');
            $table->string('filename');
            $table->bigInteger('file_size')->default(0); // In bytes
            $table->string('type')->default('manual'); // manual, scheduled, system
            $table->string('status')->default('completed'); // completed, failed, in_progress
            $table->integer('record_count')->default(0);
            $table->json('tables_backed_up')->nullable();
            $table->string('checksum')->nullable();
            $table->string('created_by')->default('System Admin');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_backups');
    }
};
