<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('status')->index();
            $table->string('demo_status')->nullable()->after('is_demo')->index();
            $table->timestamp('demo_expires_at')->nullable()->after('demo_status')->index();
            $table->timestamp('demo_purge_after')->nullable()->after('demo_expires_at')->index();
            $table->timestamp('demo_converted_at')->nullable()->after('demo_purge_after');
            $table->text('demo_notes')->nullable()->after('demo_converted_at');
            $table->unsignedBigInteger('demo_created_by')->nullable()->after('demo_notes');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'is_demo',
                'demo_status',
                'demo_expires_at',
                'demo_purge_after',
                'demo_converted_at',
                'demo_notes',
                'demo_created_by',
            ]);
        });
    }
};
