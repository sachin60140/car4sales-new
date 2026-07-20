<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name');
            $table->string('module', 50)->nullable();
            $table->string('engine', 20)->default('blade'); // blade|html
            $table->boolean('requires_admin_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('document_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_template_id')->constrained('document_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('view')->nullable();  // blade view name
            $table->longText('body')->nullable(); // html body when engine=html
            $table->json('fields')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['document_template_id', 'version']);
        });

        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 40)->unique();
            $table->foreignId('document_template_version_id')->nullable()->constrained('document_template_versions')->nullOnDelete();
            $table->string('template_key', 60)->nullable();
            $table->nullableMorphs('subject');
            $table->string('file_path');
            $table->string('qr_payload')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['template_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_documents');
        Schema::dropIfExists('document_template_versions');
        Schema::dropIfExists('document_templates');
    }
};
