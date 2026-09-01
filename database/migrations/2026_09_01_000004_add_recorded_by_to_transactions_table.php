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
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'recorded_by')) {
                $table->string('recorded_by')->nullable()->after('status')->default('System');
            }
            if (!Schema::hasColumn('transactions', 'notes')) {
                $table->text('notes')->nullable()->after('recorded_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['recorded_by', 'notes']);
        });
    }
};
