<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course unit catalogue (ACD-04) and reference material (ACD-02, EXM-03).
 *
 * A course unit is the thing a teacher registers, attaches reference material
 * and marking guides to, and then sets examinations against. It hangs off an
 * org unit for structure but is deliberately not an org unit itself, because
 * examinations, enrolments and marks all reference it directly.
 *
 * course_resources holds the uploaded past papers, marking guides and scheme
 * documents. ACD-02 asks for a course catalogue; EXM-03 asks for a marking
 * guide as an uploaded document plus structured entries. Storing the document
 * at course level means one guide serves every exam built on that course.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Owning department or programme. Nullable because a solo teacher
            // has no faculty structure to place it under.
            $table->foreignId('org_unit_id')->nullable()->constrained()->nullOnDelete();

            // Unique per tenant per the requirement.
            $table->string('code');
            $table->string('title');
            $table->text('description')->nullable();

            // Credit units, e.g. 3.0. Stored as a string column in the
            // existing style; the value is validated in the Form Request.
            $table->decimal('credit_units', total: 4, places: 2)->default(0);
            $table->string('level')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['institution_id', 'code']);
            $table->index(['institution_id', 'is_active']);
        });

        // ACD-04: responsible lecturers or teachers. A course unit can have
        // several, and one teacher can hold several course units.
        Schema::create('course_unit_teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // The lecturer accountable for the course, used when a marker must
            // be assigned by default.
            $table->boolean('is_responsible')->default(false);
            $table->timestamps();

            $table->unique(['course_unit_id', 'user_id']);
        });

        // Uploaded reference material and marking guides (ACD-02, EXM-03).
        Schema::create('course_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('kind');

            // Storage keys, never a public path. A document lives in object
            // storage and is served through a short-lived signed URL.
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            // EXM-07 requires guides to be versioned so a change after marking
            // has begun can be identified without losing earlier results.
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_current')->default(true);

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['course_unit_id', 'kind', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_resources');
        Schema::dropIfExists('course_unit_teachers');
        Schema::dropIfExists('course_units');
    }
};
