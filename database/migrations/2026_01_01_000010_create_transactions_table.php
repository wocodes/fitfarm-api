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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('pricing_plan_id')->nullable()->constrained('pricing_plans')->onDelete('set null');
            $table->decimal('amount', 10, 2);
            $table->decimal('platform_fee', 10, 2)->default(0.00); // 1% platform fee for free_tier
            $table->decimal('net_amount', 10, 2); // Amount disbursed to gym
            $table->string('currency')->default('NGN');
            $table->string('payment_method')->default('paystack');
            $table->string('reference')->nullable();
            $table->enum('status', ['pending', 'successful', 'failed'])->default('pending');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
