<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer KYC identity numbers (Aadhaar / PAN). Sensitive — only exposed to
 * users with the `customers.view-kyc` permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('aadhaar_number', 20)->nullable()->after('dob');
            $table->string('pan_number', 15)->nullable()->after('aadhaar_number');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['aadhaar_number', 'pan_number']);
        });
    }
};
