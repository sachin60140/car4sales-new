<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_number', 30)->unique();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('type', 20)->default('internal'); // internal|external
            $table->text('description')->nullable();
            $table->decimal('estimate_total', 14, 2)->default(0);
            $table->decimal('approved_total', 14, 2)->nullable();
            $table->decimal('actual_total', 14, 2)->default(0);
            $table->string('status', 20)->default('draft')->index();
            // draft|approval_pending|approved|in_progress|completed|qc_passed|qc_failed|cancelled
            $table->date('start_date')->nullable();
            $table->date('expected_completion')->nullable();
            $table->date('actual_completion')->nullable();
            $table->string('payment_status', 20)->default('unpaid'); // unpaid|partial|paid
            $table->string('qc_status', 20)->nullable(); // pending|passed|failed
            $table->foreignId('qc_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('qc_at')->nullable();
            $table->foreignId('approval_request_id')->nullable()->constrained('approval_requests')->nullOnDelete();
            $table->string('invoice_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vehicle_id', 'status']);
        });

        // Deferred FK from vehicle_expenses now that workshop_jobs exists.
        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->foreign('workshop_job_id')->references('id')->on('workshop_jobs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_expenses', function (Blueprint $table) {
            $table->dropForeign(['workshop_job_id']);
        });

        Schema::dropIfExists('workshop_jobs');
    }
};
