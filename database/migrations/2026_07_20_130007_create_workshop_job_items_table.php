<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_job_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_job_id')->constrained('workshop_jobs')->cascadeOnDelete();
            $table->string('defect')->nullable();
            $table->string('work_type', 10)->default('labour'); // part|labour
            $table->string('description');
            $table->decimal('estimate', 14, 2)->default(0);
            $table->decimal('approved_amount', 14, 2)->nullable();
            $table->decimal('actual_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('pending'); // pending|approved|done|cancelled
            $table->timestamps();

            $table->index('workshop_job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_job_items');
    }
};
