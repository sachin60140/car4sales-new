<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_inspection_id')->constrained('vehicle_inspections')->cascadeOnDelete();
            $table->foreignId('inspection_item_id')->nullable()->constrained('inspection_items')->nullOnDelete();
            $table->string('type', 10)->default('photo'); // photo|video
            $table->string('category', 40)->nullable();
            $table->string('file_path');
            $table->string('thumbnail_path')->nullable();
            $table->json('panel_marker')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('vehicle_inspection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_media');
    }
};
