<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_submissions', function (Blueprint $table) {
            // Possession → automatic stock entry (settlement_status: paid → stocked).
            $table->foreignId('vehicle_id')->nullable()->after('paid_at')->constrained('vehicles')->nullOnDelete();
            $table->json('possession')->nullable()->after('vehicle_id');
            $table->timestamp('possession_confirmed_at')->nullable()->after('possession');
            $table->foreignId('possessed_by')->nullable()->after('possession_confirmed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('possessed_by');
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropColumn(['possession', 'possession_confirmed_at']);
        });
    }
};
