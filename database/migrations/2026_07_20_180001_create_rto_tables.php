<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rto_cases', function (Blueprint $table) {
            $table->id();
            $table->string('rto_number', 30)->unique();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('delivery_id')->nullable()->constrained('deliveries')->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->foreignId('buyer_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('from_rto')->nullable();
            $table->string('to_rto')->nullable();
            $table->date('sale_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('agent_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->date('expected_completion')->nullable();
            $table->string('application_number')->nullable();
            $table->decimal('hold_amount', 14, 2)->default(0);
            $table->string('status', 40)->default('case_created')->index();
            $table->string('rc_copy_path')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assigned_to', 'status']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('rto_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rto_case_id')->constrained('rto_cases')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['rto_case_id', 'created_at']);
        });

        Schema::create('rto_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rto_case_id')->constrained('rto_cases')->cascadeOnDelete();
            $table->string('type', 40); // form_29|form_30|form_35|noc|insurance|rc|invoice|other
            $table->string('file_path')->nullable();
            $table->string('status', 20)->default('pending'); // pending|received|submitted|verified
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rto_case_id', 'type']);
        });

        // Movement of every original document (who holds it now).
        Schema::create('rto_document_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rto_case_id')->constrained('rto_cases')->cascadeOnDelete();
            $table->string('document');
            $table->string('from_holder')->nullable();
            $table->string('to_holder');
            $table->foreignId('moved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['rto_case_id', 'created_at']);
        });

        Schema::create('rto_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rto_case_id')->constrained('rto_cases')->cascadeOnDelete();
            $table->string('head', 40); // transfer_fee|noc_fee|smart_card|agent_fee|other
            $table->decimal('amount', 14, 2);
            $table->string('reference')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rto_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rto_case_id')->constrained('rto_cases')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('reason');
            $table->string('status', 20)->default('held'); // held|released
            $table->foreignId('held_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rto_holds');
        Schema::dropIfExists('rto_expenses');
        Schema::dropIfExists('rto_document_movements');
        Schema::dropIfExists('rto_documents');
        Schema::dropIfExists('rto_status_histories');
        Schema::dropIfExists('rto_cases');
    }
};
