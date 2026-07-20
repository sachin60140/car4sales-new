<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('enquiry_number', 30)->unique();
            $table->string('type', 20); // vehicle|test_drive|finance|callback|contact|sell_car|booking_interest
            $table->string('name');
            $table->string('mobile', 20)->index();
            $table->string('email')->nullable();
            $table->string('city', 100)->nullable();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->text('message')->nullable();
            $table->boolean('consent')->default(false);
            $table->timestamp('otp_verified_at')->nullable();
            $table->string('source', 40)->default('website');
            $table->string('campaign')->nullable();
            $table->json('utm')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('sales_lead_id')->nullable(); // wired when CRM (Phase 5) lands
            $table->foreignId('purchase_lead_id')->nullable()->constrained('purchase_leads')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('new'); // new|contacted|converted|closed|spam
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_enquiries');
    }
};
