<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_drives', function (Blueprint $table) {
            $table->id();
            $table->string('td_number', 30)->unique();
            $table->foreignId('sales_lead_id')->nullable()->constrained('sales_leads')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('driving_licence_number')->nullable();
            $table->string('driving_licence_path')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->unsignedInteger('start_odometer')->nullable();
            $table->unsignedInteger('end_odometer')->nullable();
            $table->string('fuel_level', 20)->nullable();
            $table->string('route')->nullable();
            $table->foreignId('accompanied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_signature_path')->nullable();
            $table->boolean('damage_acknowledged')->default(false);
            $table->text('feedback')->nullable();
            $table->string('status', 20)->default('scheduled'); // scheduled|in_progress|completed|cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sales_lead_id', 'scheduled_at']);
            $table->index(['vehicle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_drives');
    }
};
