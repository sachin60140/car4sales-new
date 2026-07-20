<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('seller_code', 30)->unique();
            $table->string('type', 20)->default('individual'); // individual|dealer|company
            $table->string('name');
            $table->string('mobile', 20)->index();
            $table->string('alt_mobile', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pin_code', 10)->nullable();
            $table->string('gst_number', 20)->nullable();
            $table->string('pan_number', 20)->nullable();
            $table->string('bank_account_name')->nullable();
            $table->text('bank_account_number')->nullable(); // encrypted
            $table->string('bank_ifsc', 20)->nullable();
            $table->string('bank_name')->nullable();
            $table->boolean('is_blacklisted')->default(false);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};
