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
            $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_phone');
            $table->string('occupation')->nullable()->after('emergency_contact_relationship');
            $table->string('nin')->nullable()->after('occupation');
            $table->string('blood_pressure')->nullable()->after('genotype');
            $table->text('medical_conditions')->nullable()->after('blood_pressure');
            $table->text('other_ailments')->nullable()->after('medical_conditions');
            $table->string('hmo_provider')->nullable()->after('other_ailments');
            $table->string('hmo_number')->nullable()->after('hmo_provider');
            $table->string('next_of_kin_name')->nullable()->after('hmo_number');
            $table->string('next_of_kin_phone')->nullable()->after('next_of_kin_name');
            $table->string('next_of_kin_relationship')->nullable()->after('next_of_kin_phone');
            $table->text('next_of_kin_address')->nullable()->after('next_of_kin_relationship');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'emergency_contact_relationship',
                'occupation',
                'nin',
                'blood_pressure',
                'medical_conditions',
                'other_ailments',
                'hmo_provider',
                'hmo_number',
                'next_of_kin_name',
                'next_of_kin_phone',
                'next_of_kin_relationship',
                'next_of_kin_address',
            ]);
        });
    }
};
