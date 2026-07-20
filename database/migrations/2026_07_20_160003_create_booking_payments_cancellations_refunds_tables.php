<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 30)->unique();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('type', 20)->default('booking'); // token|booking|advance|balance|refund
            $table->decimal('amount', 14, 2);
            $table->string('method', 30)->default('cash'); // cash|upi|card|bank_transfer|cheque
            $table->string('reference')->nullable();
            $table->string('proof_path')->nullable();
            $table->string('status', 20)->default('received'); // received|reversed
            $table->foreignId('reversal_of')->nullable()->constrained('booking_payments')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'created_at']);
        });

        Schema::create('booking_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('reason');
            $table->decimal('refund_amount', 14, 2)->default(0);
            $table->decimal('forfeit_amount', 14, 2)->default(0);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('status', 20)->default('requested'); // requested|approved|rejected
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number', 30)->unique();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('booking_cancellation_id')->nullable()->constrained('booking_cancellations')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method', 30)->nullable();
            $table->string('reference')->nullable();
            $table->string('status', 20)->default('pending'); // pending|approved|paid|rejected
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('booking_cancellations');
        Schema::dropIfExists('booking_payments');
    }
};
