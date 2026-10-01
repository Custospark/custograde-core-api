<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable grading schemes (ACD-05).
 *
 * A scheme is a set of bands mapping a score percentage to a grade, grade point
 * and remark, with a pass mark. Bands carry an explicit range so the service can
 * reject overlaps and gaps at write time, which the requirement calls for and a
 * database constraint cannot express usefully.
 *
 * MRK-04 requires the scheme version to be recorded with the result, so a
 * result can always be re-derived against the boundaries in force when it was
 * produced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            // Percentage at or above which a candidate passes, 0 to 100.
            $table->decimal('pass_mark', total: 5, places: 2)->default(50);

            // ACD-05 requires effective dates so a scheme can be superseded
            // without rewriting the results produced under the old one.
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->boolean('is_default')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'is_default']);
        });

        Schema::create('grade_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_scheme_id')->constrained()->cascadeOnDelete();

            // "A", "Distinction", "Pass". Shown on mark sheets.
            $table->string('grade');
            $table->decimal('grade_point', total: 4, places: 2)->nullable();
            $table->text('remark')->nullable();

            // Inclusive percentage range. Bands are contiguous by convention:
            // one band ends where the next begins.
            $table->decimal('min_percent', total: 5, places: 2);
            $table->decimal('max_percent', total: 5, places: 2);

            // Display order, highest band first.
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['grading_scheme_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_bands');
        Schema::dropIfExists('grading_schemes');
    }
};
