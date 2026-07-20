<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('type', 20)->default('cash'); // cash|bank|upi
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('customer_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamps();
        });

        // Append-only ledger. Corrections are made with reversal rows referencing
        // the original entry — posted entries are never edited or deleted.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_ledger_id')->constrained('customer_ledgers')->cascadeOnDelete();
            $table->string('type', 10); // debit|credit
            $table->string('head', 30); // selling_price|booking_amount|down_payment|finance_amount|exchange|discount|insurance|accessories|rto_charges|other|payment|refund
            $table->decimal('amount', 14, 2);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('reversal_of')->nullable()->constrained('ledger_entries')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->useCurrent();
            $table->string('remarks')->nullable();

            $table->index(['customer_ledger_id', 'posted_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('other_charges', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->foreignId('generated_document_id')->nullable()->constrained('generated_documents')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 30)->unique();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('booking_payment_id')->nullable()->constrained('booking_payments')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->foreignId('generated_document_id')->nullable()->constrained('generated_documents')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // Booking payments now record their source account.
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('method')->constrained('payment_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
        });
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('customer_ledgers');
        Schema::dropIfExists('payment_accounts');
    }
};
