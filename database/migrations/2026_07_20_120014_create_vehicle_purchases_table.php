<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number', 30)->unique();
            $table->foreignId('purchase_lead_id')->constrained('purchase_leads')->cascadeOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            // Set once the stock record is created (no FK to avoid a cycle with vehicles).
            $table->unsignedBigInteger('vehicle_id')->nullable()->index();
            $table->decimal('agreed_price', 14, 2)->default(0);
            $table->decimal('initial_expenses', 14, 2)->default(0);
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->foreignId('agreement_document_id')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->string('status', 30)->default('approved')->index();
            // approved|agreement_generated|payment_pending|possession_pending|completed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_purchases');
    }
};
