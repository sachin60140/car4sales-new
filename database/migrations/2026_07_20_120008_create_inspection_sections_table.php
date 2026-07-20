<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_inspection_id')->constrained('vehicle_inspections')->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('label');
            $table->unsignedTinyInteger('rating')->nullable(); // 1-5
            $table->string('status', 10)->nullable(); // pass|fail|na
            $table->text('remarks')->nullable();
            $table->decimal('repair_estimate', 14, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('vehicle_inspection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_sections');
    }
};
