<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forces a staff member to replace the password an administrator gave them.
 *
 * Without this, adding somebody means sending them a working password by email,
 * which is a shared secret from the moment it is sent and often survives in the
 * thread for years. The flag means the account exists but is not really usable
 * until its owner has chosen a password of their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->foreignId('updated_by')->nullable()->after('must_change_password')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn('must_change_password');
        });
    }
};