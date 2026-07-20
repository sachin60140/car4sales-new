<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_lead_id')->constrained('purchase_leads')->cascadeOnDelete();
            $table->string('type', 40); // rc_original|rc_copy|insurance|puc|tax|fitness|permit|hypothecation|bank_noc|form_35|challan|blacklist|service_history|keys|purchase_invoice
            $table->string('status', 20)->default('pending'); // pending|received|verified|rejected|expired|not_applicable
            $table->string('file_path')->nullable();
            $table->string('number')->nullable();
            $table->date('valid_till')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['purchase_lead_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_verifications');
    }
};
