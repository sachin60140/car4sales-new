<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarantees at most one active (non-cancelled, non-deleted) delivery per booking
 * at the database level, closing the check-then-insert race in DeliveryAction.
 *
 * A generated column mirrors the application's "active" predicate: it equals the
 * booking_id while the delivery is live and NULL once cancelled or soft-deleted.
 * A UNIQUE index over it enforces one live delivery per booking while allowing
 * unlimited NULLs (cancelled/deleted rows never block a fresh delivery).
 *
 * A VIRTUAL (not STORED) column is used so the migration runs on both MariaDB and
 * SQLite (SQLite only permits VIRTUAL generated columns via ALTER TABLE). The
 * expression avoids backtick quoting so both grammars parse it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('active_booking_id')->nullable()
                ->virtualAs("case when status <> 'cancelled' and deleted_at is null then booking_id else null end");
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->unique('active_booking_id', 'deliveries_one_active_per_booking');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropUnique('deliveries_one_active_per_booking');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn('active_booking_id');
        });
    }
};
