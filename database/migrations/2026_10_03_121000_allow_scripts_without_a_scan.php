<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allows a script row to exist before any paper has been received.
 *
 * The original schema declared `original_path`, `original_hash` and
 * `original_name` as NOT NULL, which quietly encodes an assumption: a script row
 * means a scan is already on disk. That held until answer sheets were issued
 * (SHT-01), because issuing a sheet creates the script row up front so that a
 * later scan can resolve to a candidate instead of arriving unidentified.
 *
 * The columns are relaxed rather than filled with placeholders. A placeholder
 * path would be a lie that later code could not distinguish from a real file:
 * a "signed image URL" for a sheet nobody has handed in would either 404 at the
 * worst moment or, worse, succeed against an empty file.
 *
 * Null now means "no scan received yet", which is exactly what `issued` says.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scripts', function (Blueprint $table): void {
            $table->string('original_path')->nullable()->change();
            $table->string('original_hash', 64)->nullable()->change();
            $table->string('original_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Not reversible in practice: any row issued without a scan cannot be
        // given a truthful value, so rolling back would fail on real data. Left
        // failing loudly rather than inventing one.
        Schema::table('scripts', function (Blueprint $table): void {
            $table->string('original_path')->nullable(false)->change();
            $table->string('original_hash', 64)->nullable(false)->change();
            $table->string('original_name')->nullable(false)->change();
        });
    }
};