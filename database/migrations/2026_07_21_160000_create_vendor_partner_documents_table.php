<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor-partner KYC: the partner's own identity/business documents, uploadable
 * by the partner or an admin and verified by staff. A partner cannot be
 * activated until every required document is verified. `kyc_status` on the
 * profile is a denormalised summary (pending | submitted | verified).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_partner_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_profile_id')->constrained('vendor_profiles')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('file_path');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('number', 100)->nullable();
            $table->string('status', 20)->default('pending'); // pending | verified | rejected
            $table->text('remarks')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['vendor_profile_id', 'type']);
        });

        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->string('kyc_status', 20)->default('pending')->after('status'); // pending | submitted | verified
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_partner_documents');

        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->dropColumn('kyc_status');
        });
    }
};
