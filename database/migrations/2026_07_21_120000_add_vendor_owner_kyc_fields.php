<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_submissions', function (Blueprint $table) {
            // Owner (seller) details captured after approval — used to generate the
            // dynamic agreement. Kept on the submission only; NOT copied to the lead.
            $table->string('owner_name')->nullable()->after('settlement_status');
            $table->string('owner_phone', 20)->nullable()->after('owner_name');
            $table->string('owner_email')->nullable()->after('owner_phone');
            $table->text('owner_address')->nullable()->after('owner_email');
            $table->string('owner_pan', 15)->nullable()->after('owner_address');

            // Owner-document (KYC) review trail.
            $table->timestamp('kyc_submitted_at')->nullable()->after('owner_pan');
            $table->timestamp('kyc_approved_at')->nullable()->after('kyc_submitted_at');
            $table->foreignId('kyc_approved_by')->nullable()->after('kyc_approved_at')->constrained('users')->nullOnDelete();
            $table->string('kyc_remarks', 1000)->nullable()->after('kyc_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kyc_approved_by');
            $table->dropColumn([
                'owner_name', 'owner_phone', 'owner_email', 'owner_address', 'owner_pan',
                'kyc_submitted_at', 'kyc_approved_at', 'kyc_remarks',
            ]);
        });
    }
};
