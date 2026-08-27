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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('tenant_users')->onDelete('set null');
            $table->string('user_name')->nullable();
            $table->string('action'); // e.g. PLAN_PAUSED, PLAN_EXTENDED, MEMBER_CREATED
            $table->string('target_type')->nullable(); // e.g. Member, MemberPlan, Transaction
            $table->string('target_id')->nullable();
            $table->text('description')->nullable();
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('tenant_user_id')->constrained('tenant_users')->onDelete('cascade');
            $table->timestamp('clock_in')->useCurrent();
            $table->timestamp('clock_out')->nullable();
            $table->string('status')->default('active'); // active, completed
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_attendances');
        Schema::dropIfExists('audit_logs');
    }
};
