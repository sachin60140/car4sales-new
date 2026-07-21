<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_submissions', function (Blueprint $table) {
            // not_started | agreement_ready | payment_requested | paid
            $table->string('settlement_status', 20)->default('not_started')->after('purchase_lead_id')->index();

            // Vendor's payout bank details (captured at payment request).
            $table->string('bank_account_name')->nullable()->after('settlement_status');
            $table->string('bank_account_number', 30)->nullable()->after('bank_account_name');
            $table->string('bank_ifsc', 15)->nullable()->after('bank_account_number');
            $table->string('bank_name')->nullable()->after('bank_ifsc');
            $table->timestamp('payment_requested_at')->nullable()->after('bank_name');

            // Payment recorded by staff.
            $table->decimal('payment_amount', 14, 2)->nullable()->after('payment_requested_at');
            $table->string('payment_mode', 20)->nullable()->after('payment_amount'); // neft|upi|cheque|cash
            $table->string('payment_reference')->nullable()->after('payment_mode');   // UTR / cheque no.
            $table->date('payment_date')->nullable()->after('payment_reference');
            $table->foreignId('paid_by')->nullable()->after('payment_date')->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->after('paid_by');
        });

        // Widen media type to fit 'cancelled_cheque' / 'payment_proof'.
        Schema::table('vendor_submission_media', function (Blueprint $table) {
            $table->string('type', 20)->default('gallery')->change();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_by');
            $table->dropColumn([
                'settlement_status', 'bank_account_name', 'bank_account_number', 'bank_ifsc',
                'bank_name', 'payment_requested_at', 'payment_amount', 'payment_mode',
                'payment_reference', 'payment_date', 'paid_at',
            ]);
        });

        Schema::table('vendor_submission_media', function (Blueprint $table) {
            $table->string('type', 10)->default('gallery')->change();
        });
    }
};
