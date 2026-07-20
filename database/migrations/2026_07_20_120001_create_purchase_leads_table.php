<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_number', 30)->unique();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->nullOnDelete();

            // Snapshot of seller contact at capture time (public form may pre-date seller record).
            $table->string('seller_name');
            $table->string('seller_type', 20)->default('individual');
            $table->string('mobile', 20)->index();
            $table->string('alt_mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('pin_code', 10)->nullable();
            $table->string('source', 40)->default('manual');

            // Vehicle details.
            $table->string('registration_number', 20)->nullable()->index();
            $table->string('make', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('variant', 100)->nullable();
            $table->unsignedSmallInteger('manufacturing_year')->nullable();
            $table->string('fuel_type', 30)->nullable();
            $table->string('transmission', 30)->nullable();
            $table->unsignedInteger('odometer_km')->nullable();
            $table->decimal('expected_price', 14, 2)->nullable();
            $table->string('loan_status', 30)->default('none'); // none|active|closed_pending_noc

            $table->string('inspection_location')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('priority', 20)->default('normal'); // low|normal|high|hot
            $table->timestamp('next_follow_up_at')->nullable();
            $table->string('status', 40)->default('new')->index();
            $table->string('lost_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->json('utm')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_leads');
    }
};
