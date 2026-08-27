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
        Schema::table('licenses', function (Blueprint $table) {
            if (!Schema::hasColumn('licenses', 'user_email')) {
                $table->string('user_email')->nullable()->after('key');
            }
            if (!Schema::hasColumn('licenses', 'mode')) {
                $table->string('mode')->default('trial')->after('tenant_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            if (Schema::hasColumn('licenses', 'user_email')) {
                $table->dropColumn('user_email');
            }
            if (Schema::hasColumn('licenses', 'mode')) {
                $table->dropColumn('mode');
            }
        });
    }
};
