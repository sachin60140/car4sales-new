<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Central approval inbox used by every module (purchase, discount, refund...).
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('approval_number', 30)->unique();
            $table->string('module', 50);      // purchase-approval|discount|seller-payment|...
            $table->string('type', 50)->nullable();
            $table->nullableMorphs('subject');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('requested_amount', 14, 2)->nullable();
            $table->decimal('recommended_amount', 14, 2)->nullable();
            $table->decimal('approved_amount', 14, 2)->nullable();
            $table->text('reason')->nullable();
            $table->json('reasons')->nullable();     // risk flags
            $table->json('attachments')->nullable();
            $table->string('status', 20)->default('pending'); // pending|approved|rejected|cancelled
            $table->foreignId('current_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['module', 'status']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
