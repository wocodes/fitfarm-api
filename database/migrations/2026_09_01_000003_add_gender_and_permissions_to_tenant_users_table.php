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
        Schema::table('tenant_users', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_users', 'gender')) {
                $table->string('gender')->nullable()->default('Male')->after('phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_users', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_users', 'gender')) {
                $table->dropColumn('gender');
            }
        });
    }
};
