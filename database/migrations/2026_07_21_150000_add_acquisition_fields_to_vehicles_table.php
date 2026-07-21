<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acquisition/source details for a vehicle — who it was purchased from and by
 * whom. Mainly relevant for manually added stock (the purchase-lead and vendor
 * pipelines carry their own richer records), but available on every vehicle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('acquisition_source', 20)->nullable()->after('vehicle_purchase_id');
            $table->string('seller_name', 150)->nullable()->after('acquisition_source');
            $table->string('seller_contact', 100)->nullable()->after('seller_name');
            $table->foreignId('purchased_by')->nullable()->after('seller_contact')->constrained('users')->nullOnDelete();
            $table->date('purchased_at')->nullable()->after('purchased_by');
            $table->string('purchase_reference', 100)->nullable()->after('purchased_at');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchased_by');
            $table->dropColumn(['acquisition_source', 'seller_name', 'seller_contact', 'purchased_at', 'purchase_reference']);
        });
    }
};
