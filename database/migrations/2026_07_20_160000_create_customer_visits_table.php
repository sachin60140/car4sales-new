<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_visits', function (Blueprint $table) {
            $table->id();
            $table->string('visit_number', 30)->unique();
            $table->foreignId('sales_lead_id')->nullable()->constrained('sales_leads')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->boolean('confirmed')->default(false);
            $table->timestamp('arrived_at')->nullable();
            $table->foreignId('attended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('interested_vehicle_ids')->nullable();
            $table->string('outcome')->nullable();
            $table->string('next_action')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('scheduled'); // scheduled|confirmed|completed|no_show|cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sales_lead_id', 'scheduled_at']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_visits');
    }
};
