<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_possessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_purchase_id')->unique()->constrained('vehicle_purchases')->cascadeOnDelete();

            // Possession checklist booleans.
            $table->boolean('vehicle_received')->default(false);
            $table->boolean('original_rc_received')->default(false);
            $table->boolean('insurance_received')->default(false);
            $table->boolean('puc_received')->default(false);
            $table->boolean('noc_received')->default(false);
            $table->boolean('form_35_received')->default(false);
            $table->boolean('main_key')->default(false);
            $table->boolean('spare_key')->default(false);
            $table->boolean('service_book')->default(false);
            $table->boolean('tool_kit')->default(false);
            $table->boolean('spare_wheel')->default(false);
            $table->boolean('accessories')->default(false);

            $table->unsignedInteger('odometer_km')->nullable();
            $table->string('fuel_level', 20)->nullable();
            $table->string('seller_signature_path')->nullable();
            $table->string('employee_signature_path')->nullable();
            $table->timestamp('possessed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_possessions');
    }
};
