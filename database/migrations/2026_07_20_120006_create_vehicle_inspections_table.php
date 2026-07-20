<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_inspections', function (Blueprint $table) {
            $table->id();
            $table->string('inspection_number', 30)->unique();
            $table->foreignId('purchase_lead_id')->constrained('purchase_leads')->cascadeOnDelete();
            $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('location')->nullable();
            $table->unsignedInteger('odometer_km')->nullable();
            $table->string('overall_grade', 2)->nullable(); // A|B|C|D
            $table->string('result', 40)->nullable(); // recommended|recommended_with_repairs|management_approval|not_recommended
            $table->decimal('total_repair_estimate', 14, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('report_path')->nullable();
            $table->string('status', 30)->default('scheduled')->index(); // scheduled|in_progress|submitted|reviewed|cancelled
            $table->timestamps();
            $table->softDeletes();

            $table->index(['purchase_lead_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_inspections');
    }
};
