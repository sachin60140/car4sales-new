<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_lost_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('category', 40)->default('general'); // price|finance|competitor|not_serious|general
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_lost_reasons');
    }
};
