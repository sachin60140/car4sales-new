<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_submissions', function (Blueprint $table) {
            // Vehicle identity + loan status captured during owner-KYC.
            $table->string('chassis_number', 40)->nullable()->after('registration_state');
            $table->boolean('has_hypothecation')->default(false)->after('owner_pan');
            // Keys recorded in the condition report: both | one | none.
            $table->string('keys_available', 10)->nullable()->after('overall_rating');
            // Per-document staff verification: { type: { status, number, valid_till, remarks } }.
            $table->json('document_verifications')->nullable()->after('kyc_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_submissions', function (Blueprint $table) {
            $table->dropColumn(['chassis_number', 'has_hypothecation', 'keys_available', 'document_verifications']);
        });
    }
};
