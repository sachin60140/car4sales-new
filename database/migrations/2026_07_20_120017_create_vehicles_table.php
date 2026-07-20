<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('stock_number', 30)->unique();
            $table->foreignId('vehicle_purchase_id')->nullable()->constrained('vehicle_purchases')->nullOnDelete();

            $table->string('registration_number', 20)->nullable();
            $table->string('chassis_number', 40)->nullable();
            $table->string('engine_number', 40)->nullable();
            $table->string('make', 100);
            $table->string('model', 100);
            $table->string('variant', 100)->nullable();
            $table->unsignedSmallInteger('manufacturing_year')->nullable();
            $table->unsignedSmallInteger('registration_year')->nullable();
            $table->string('registration_state', 100)->nullable();
            $table->string('fuel_type', 30)->nullable();
            $table->string('transmission', 30)->nullable();
            $table->string('body_type', 30)->nullable();
            $table->string('color', 40)->nullable();
            $table->unsignedInteger('odometer_km')->nullable();
            $table->unsignedTinyInteger('ownership_serial')->nullable();
            $table->string('insurance_status', 30)->nullable();
            $table->date('insurance_valid_till')->nullable();

            $table->decimal('purchase_price', 14, 2)->default(0);
            $table->decimal('landed_cost', 14, 2)->default(0);
            $table->decimal('minimum_selling_price', 14, 2)->nullable();
            $table->decimal('asking_price', 14, 2)->nullable();

            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('parking_location')->nullable();
            $table->string('inspection_grade', 2)->nullable();
            $table->boolean('refurb_required')->default(false);
            $table->string('status', 30)->default('in_stock')->index();

            $table->boolean('published_web')->default(false);
            $table->boolean('published_mobile')->default(false);
            $table->string('slug')->nullable()->unique();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->json('key_features')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedBigInteger('reserved_booking_id')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index('registration_number');
            $table->index('chassis_number');
            $table->index('engine_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
