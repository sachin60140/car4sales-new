<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number', 30)->unique();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('status', 20)->default('approval_pending')->index(); // approval_pending|approved|delivered|cancelled

            // Delivery approval checklist (spec §24).
            $table->boolean('chk_booking_confirmed')->default(false);
            $table->boolean('chk_kyc_verified')->default(false);
            $table->boolean('chk_payment_complete')->default(false);
            $table->boolean('chk_finance_disbursed')->default(false);
            $table->boolean('chk_quality_check')->default(false);
            $table->boolean('chk_insurance')->default(false);
            $table->boolean('chk_rto_papers_signed')->default(false);
            $table->boolean('chk_accessories')->default(false);
            $table->boolean('chk_cleaned')->default(false);
            $table->boolean('chk_documents_prepared')->default(false);

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Handover checklist (spec §24).
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedInteger('odometer')->nullable();
            $table->string('fuel_level', 20)->nullable();
            $table->boolean('dc_keys')->default(false);
            $table->boolean('dc_spare_key')->default(false);
            $table->boolean('dc_rc_copy')->default(false);
            $table->boolean('dc_insurance')->default(false);
            $table->boolean('dc_invoice')->default(false);
            $table->boolean('dc_tool_kit')->default(false);
            $table->boolean('dc_spare_wheel')->default(false);
            $table->boolean('dc_accessories')->default(false);
            $table->string('customer_photo_path')->nullable();
            $table->string('delivery_photo_path')->nullable();
            $table->string('customer_signature_path')->nullable();
            $table->string('employee_signature_path')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
        });

        Schema::create('delivery_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('file_path')->nullable();
            $table->boolean('handed_over')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_documents');
        Schema::dropIfExists('deliveries');
    }
};
