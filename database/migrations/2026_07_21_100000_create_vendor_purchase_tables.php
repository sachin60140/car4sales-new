<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sourcing/purchase vendor accounts — a login user + partner profile.
        Schema::create('vendor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('company_name')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('city')->nullable();
            $table->string('gst_number', 20)->nullable();
            // pending_activation | active | rejected | suspended
            $table->string('status', 20)->default('pending_activation')->index();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // A vehicle a vendor offers to sell to the dealership.
        Schema::create('vendor_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('submission_number', 30)->unique();
            $table->foreignId('vendor_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('make');
            $table->string('model');
            $table->string('variant')->nullable();
            $table->unsignedSmallInteger('manufacturing_year')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('registration_state', 40)->nullable();
            $table->string('fuel_type', 20)->nullable();
            $table->string('transmission', 20)->nullable();
            $table->string('color', 40)->nullable();
            $table->unsignedInteger('odometer_km')->nullable();
            $table->unsignedTinyInteger('ownership_serial')->nullable();

            $table->decimal('expected_amount', 14, 2)->default(0);
            $table->unsignedTinyInteger('overall_rating')->nullable(); // 1..5
            $table->text('overall_remark')->nullable();

            // draft | pending_review | approved | rejected
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();

            // Set when approved — the lead this submission became.
            $table->foreignId('purchase_lead_id')->nullable()->constrained('purchase_leads')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_user_id', 'status']);
        });

        // Condition checklist — one row per assessed item.
        Schema::create('vendor_submission_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_submission_id')->constrained('vendor_submissions')->cascadeOnDelete();
            $table->string('section', 60);
            $table->string('label');
            $table->string('result', 10)->default('na'); // pass | fail | na
            $table->unsignedTinyInteger('rating')->nullable(); // 1..5
            $table->string('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->index(['vendor_submission_id', 'sort_order']);
        });

        // Uploaded images — general gallery + damaged-part shots.
        Schema::create('vendor_submission_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_submission_id')->constrained('vendor_submissions')->cascadeOnDelete();
            $table->string('type', 10)->default('gallery'); // gallery | damage
            $table->string('file_path');
            $table->string('thumbnail_path')->nullable();
            $table->string('caption')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vendor_submission_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_submission_media');
        Schema::dropIfExists('vendor_submission_items');
        Schema::dropIfExists('vendor_submissions');
        Schema::dropIfExists('vendor_profiles');
    }
};
