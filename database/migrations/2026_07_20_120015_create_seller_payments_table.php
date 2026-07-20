<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 30)->unique();
            $table->foreignId('vehicle_purchase_id')->constrained('vehicle_purchases')->cascadeOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->string('type', 30)->default('advance');
            // token|advance|full|balance|hold|loan_closure|bank_payment|owner_payment|brokerage|adjustment
            $table->decimal('amount', 14, 2);
            $table->string('method', 30)->nullable(); // cash|neft|rtgs|imps|cheque|upi
            $table->string('payment_account')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('recipient_type', 30)->default('seller'); // seller|bank|registered_owner|broker
            $table->json('recipient_details')->nullable();
            $table->string('status', 20)->default('pending_approval');
            // draft|pending_approval|approved|paid|reversed
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();  // maker
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); // checker
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversal_of')->nullable()->constrained('seller_payments')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['vehicle_purchase_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_payments');
    }
};
