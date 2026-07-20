<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // In-app notification records, one row per recipient.
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 60);              // event key, e.g. booking.confirmed
            $table->string('level', 12)->default('info'); // info|success|warning|critical
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('action_url')->nullable(); // deep link into the panel
            $table->json('data')->nullable();          // structured payload for the client
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });

        // Per-channel delivery attempts (mail/sms/whatsapp/push). Records the
        // outcome even when the driver is a no-op log stub, so the multi-channel
        // fan-out is auditable without a live provider.
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->string('channel', 20);            // database|mail|sms|whatsapp|push
            $table->string('driver', 30);             // log|null|smtp|...
            $table->string('status', 20)->default('queued'); // queued|sent|failed|skipped
            $table->string('destination')->nullable(); // email/phone/token used
            $table->text('response')->nullable();      // provider response / error
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['notification_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notifications');
    }
};
