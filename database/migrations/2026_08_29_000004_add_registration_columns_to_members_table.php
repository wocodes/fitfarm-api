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
        Schema::table('members', function (Blueprint $table) {
            $table->string('registration_source')->nullable()->after('status');
            $table->string('registration_status')->default('REGISTERED')->after('registration_source');
            $table->string('biometric_hash')->nullable()->after('registration_status');
            $table->date('registration_expiry_date')->nullable()->after('biometric_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['registration_source', 'registration_status', 'biometric_hash', 'registration_expiry_date']);
        });
    }
};
