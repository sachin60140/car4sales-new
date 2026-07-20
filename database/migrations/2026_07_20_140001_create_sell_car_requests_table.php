<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sell_car_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_enquiry_id')->nullable()->constrained('public_enquiries')->nullOnDelete();
            $table->string('seller_name');
            $table->string('mobile', 20);
            $table->string('city', 100)->nullable();
            $table->string('registration_number', 20)->nullable();
            $table->string('make', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('variant', 100)->nullable();
            $table->unsignedSmallInteger('manufacturing_year')->nullable();
            $table->unsignedInteger('odometer_km')->nullable();
            $table->decimal('expected_price', 14, 2)->nullable();
            $table->string('loan_status', 30)->default('none');
            $table->string('preferred_inspection_location')->nullable();
            $table->date('preferred_date')->nullable();
            $table->json('photos')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('purchase_lead_id')->nullable()->constrained('purchase_leads')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sell_car_requests');
    }
};
