<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lenders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('type', 20)->default('bank'); // bank|nbfc|captive|other
            $table->string('contact_person')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->decimal('base_interest_rate', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('finance_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number', 30)->unique();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('lender_id')->nullable()->constrained('lenders')->nullOnDelete();
            $table->json('applicant')->nullable();
            $table->json('co_applicant')->nullable();
            $table->json('guarantor')->nullable();
            $table->json('income')->nullable();
            $table->string('employer')->nullable();
            $table->decimal('loan_amount', 14, 2)->default(0);
            $table->decimal('down_payment', 14, 2)->default(0);
            $table->string('lender_application_number')->nullable();
            $table->decimal('sanction_amount', 14, 2)->nullable();
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->unsignedSmallInteger('tenure_months')->nullable();
            $table->decimal('emi', 14, 2)->nullable();
            $table->text('queries')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->decimal('disbursed_amount', 14, 2)->default(0);
            $table->string('status', 30)->default('documents_pending')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['booking_id', 'status']);
        });

        Schema::create('finance_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_application_id')->constrained('finance_applications')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['finance_application_id', 'created_at']);
        });

        Schema::create('disbursements', function (Blueprint $table) {
            $table->id();
            $table->string('disbursement_number', 30)->unique();
            $table->foreignId('finance_application_id')->constrained('finance_applications')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('utr')->nullable();
            $table->date('disbursed_on')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursements');
        Schema::dropIfExists('finance_status_histories');
        Schema::dropIfExists('finance_applications');
        Schema::dropIfExists('lenders');
    }
};
