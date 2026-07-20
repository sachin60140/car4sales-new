<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_number', 30)->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('name');
            $table->string('mobile', 20)->index();
            $table->string('alt_mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('budget_min', 14, 2)->nullable();
            $table->decimal('budget_max', 14, 2)->nullable();
            $table->foreignId('interested_vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->json('preferences')->nullable(); // make/model/fuel/body preferences
            $table->boolean('finance_required')->default(false);
            $table->boolean('exchange_required')->default(false);
            $table->string('source', 40)->default('manual');
            $table->string('campaign')->nullable();
            $table->json('utm')->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('telecaller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sales_executive_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 10)->default('normal'); // low|normal|high|hot
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->string('status', 30)->default('new')->index();
            $table->foreignId('lost_reason_id')->nullable()->constrained('lead_lost_reasons')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['telecaller_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index('next_follow_up_at');
        });

        // Now that sales_leads exists, add the deferred FK from public_enquiries.
        Schema::table('public_enquiries', function (Blueprint $table) {
            $table->foreign('sales_lead_id')->references('id')->on('sales_leads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('public_enquiries', function (Blueprint $table) {
            $table->dropForeign(['sales_lead_id']);
        });

        Schema::dropIfExists('sales_leads');
    }
};
