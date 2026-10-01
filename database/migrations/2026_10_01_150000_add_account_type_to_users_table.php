<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Personal accounts are individual teachers with no institution, so
     * institution_id stays NULL for them (AUT-06 self-tenant scoping).
     * Existing rows all came from institution registration, so the default
     * backfills them as institutional.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type')->default('institutional')->after('institution_id')->index();
        });

        DB::table('users')
            ->whereNull('institution_id')
            ->update(['account_type' => 'personal']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_type']);
            $table->dropColumn('account_type');
        });
    }
};
