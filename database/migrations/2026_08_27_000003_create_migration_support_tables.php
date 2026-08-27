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
        Schema::create('gym_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('setting_key');
            $table->text('setting_value')->nullable();
            $table->string('category')->nullable();
            $table->string('label')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'setting_key']);
        });

        Schema::create('member_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->string('subject');
            $table->text('message');
            $table->enum('type', ['SUGGESTION', 'COMPLAINT', 'ACKNOWLEDGEMENT', 'OTHER'])->default('SUGGESTION');
            $table->timestamp('date_sent')->useCurrent();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_starred')->default(false);
            $table->timestamps();
        });

        Schema::create('payment_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name');
            $table->string('code');
            $table->decimal('default_amount', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('account_type')->default('Current');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'account_number']);
        });

        Schema::create('campaign_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('subject');
            $table->text('body')->nullable();
            $table->text('target_emails')->nullable();
            $table->integer('sent_to_count')->default(0);
            $table->string('status')->default('SENT');
            $table->text('result_details')->nullable();
            $table->string('task_id')->nullable();
            $table->text('failed_emails')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_rank_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('campaign_id')->constrained('campaigns')->onDelete('cascade');
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->integer('rank_position');
            $table->integer('total_check_ins')->default(0);
            $table->integer('total_points')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_rank_snapshots');
        Schema::dropIfExists('campaign_logs');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('payment_types');
        Schema::dropIfExists('member_messages');
        Schema::dropIfExists('gym_settings');
    }
};
