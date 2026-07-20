<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->cascadeOnDelete();
            $table->foreignId('purchase_lead_id')->nullable()->constrained('purchase_leads')->cascadeOnDelete();
            $table->string('type', 40); // aadhaar|pan|address_proof|photo|photo_with_vehicle|cancelled_cheque|signature|gst|authorisation_letter|relationship_proof|poa|owner_declaration|other
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('status', 20)->default('received'); // pending|received|verified|rejected|expired|not_applicable
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['purchase_lead_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_documents');
    }
};
