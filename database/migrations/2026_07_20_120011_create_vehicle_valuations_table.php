<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_valuations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_lead_id')->constrained('purchase_leads')->cascadeOnDelete();
            $table->foreignId('vehicle_inspection_id')->nullable()->constrained('vehicle_inspections')->nullOnDelete();

            $table->decimal('market_price', 14, 2)->default(0);
            $table->decimal('expected_retail_price', 14, 2)->default(0);
            $table->decimal('seller_expected_price', 14, 2)->default(0);
            $table->decimal('repair_estimate', 14, 2)->default(0);
            $table->decimal('rto_expense', 14, 2)->default(0);
            $table->decimal('documentation_expense', 14, 2)->default(0);
            $table->decimal('transportation_expense', 14, 2)->default(0);
            $table->decimal('insurance_expense', 14, 2)->default(0);
            $table->decimal('brokerage', 14, 2)->default(0);
            $table->decimal('holding_cost', 14, 2)->default(0);
            $table->decimal('other_costs', 14, 2)->default(0);
            $table->decimal('target_profit', 14, 2)->default(0);

            // Derived + stored.
            $table->decimal('recommended_price', 14, 2)->default(0);
            $table->decimal('final_negotiated_price', 14, 2)->nullable();
            $table->decimal('expected_gross_profit', 14, 2)->default(0);
            $table->decimal('expected_net_profit', 14, 2)->default(0);
            $table->decimal('expected_margin_pct', 8, 2)->default(0);

            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft'); // draft|submitted|approved
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index('purchase_lead_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_valuations');
    }
};
