<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_lead_id')->constrained('purchase_leads')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('contact_mode', 20)->default('call'); // call|whatsapp|visit|other
            $table->string('outcome', 40)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamps();

            $table->index(['purchase_lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_followups');
    }
};
