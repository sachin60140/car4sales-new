<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('remember_token')
                ->constrained('branches')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('branch_id')
                ->constrained('departments')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->after('department_id')
                ->constrained('teams')->nullOnDelete();
            $table->string('phone', 20)->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('team_id');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->timestamp('password_changed_at')->nullable()->after('last_login_at');
            $table->boolean('force_password_change')->default(false)->after('password_changed_at');
            $table->softDeletes();

            $table->index('is_active');
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn([
                'phone', 'is_active', 'last_login_at',
                'password_changed_at', 'force_password_change', 'deleted_at',
            ]);
        });
    }
};
