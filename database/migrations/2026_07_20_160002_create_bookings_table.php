<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number', 30)->unique();
            $table->foreignId('sales_lead_id')->nullable()->constrained('sales_leads')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->decimal('selling_price', 14, 2);
            $table->decimal('booking_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->foreignId('discount_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_mode', 10)->default('cash'); // cash|finance
            $table->decimal('exchange_adjustment', 14, 2)->default(0);
            $table->timestamp('delivery_promised_at')->nullable();
            $table->foreignId('telecaller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sales_executive_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->json('accessories_promised')->nullable();
            $table->text('terms')->nullable();
            $table->string('customer_signature_path')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Fast lookup of the active booking for a vehicle (single-active-booking
            // is enforced in ConfirmBookingAction under a row lock).
            $table->index(['vehicle_id', 'status']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('booking_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['booking_id', 'created_at']);
        });

        // Link the vehicle's reserved_booking_id now that bookings exists.
        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreign('reserved_booking_id')->references('id')->on('bookings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['reserved_booking_id']);
        });

        Schema::dropIfExists('booking_status_histories');
        Schema::dropIfExists('bookings');
    }
};
