<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Answer sheets issued to candidates (SHT-01 to SHT-09).
 *
 * A sheet is a row here rather than a flag on `scripts`, because SHT-06
 * requires that a replacement sheet invalidates the earlier code and records why.
 * That is history, and a nullable column on `scripts` could only hold the latest
 * state. Keeping every issue means an audit can show which code a candidate
 * actually sat for, which is the question an examination dispute turns on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('script_sheets', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('script_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->index();

            // The opaque code printed on the sheet and encoded in its QR. Unique
            // across the application because it is what a scan is matched on,
            // and a collision would attach a paper to the wrong candidate.
            $table->string('code', 64)->unique();

            $table->unsignedSmallInteger('page_count')->default(1);
            $table->timestamp('issued_at');
            $table->timestamp('invalidated_at')->nullable();
            $table->string('invalidation_reason', 255)->nullable();

            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invalidated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Reissuing is a normal, frequent operation, so the audit lookup
            // that matters is "the current sheet for this script".
            $table->index(['script_id', 'invalidated_at'], 'script_sheets_current_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('script_sheets');
    }
};