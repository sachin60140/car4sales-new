<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Manual call logs / follow-ups.
        Schema::create('lead_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_lead_id')->constrained('sales_leads')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20)->default('call'); // call|whatsapp|sms|email|visit
            $table->string('call_outcome', 30)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sales_lead_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // Unified activity timeline (created, assigned, called, status change, note).
        Schema::create('lead_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_lead_id')->constrained('sales_leads')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30); // created|assigned|call|status|note|visit|test_drive
            $table->string('summary');
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sales_lead_id', 'created_at']);
        });

        // Assignment history (telecaller / sales executive changes).
        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_lead_id')->constrained('sales_leads')->cascadeOnDelete();
            $table->string('role', 20); // telecaller|sales_executive
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sales_lead_id', 'created_at']);
        });

        // Status history.
        Schema::create('lead_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_lead_id')->constrained('sales_leads')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sales_lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_status_histories');
        Schema::dropIfExists('lead_assignments');
        Schema::dropIfExists('lead_activities');
        Schema::dropIfExists('lead_followups');
    }
};
