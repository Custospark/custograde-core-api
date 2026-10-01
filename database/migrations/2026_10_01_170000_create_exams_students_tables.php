<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Examinations, questions and the roster (EXM-01, EXM-02, STU-01, STU-03).
 *
 * An examination is the container the whole marking chain hangs off: a teacher
 * defines questions and marks, candidates are enrolled, scripts are captured
 * against the exam, and every mark is stored against a question on a script.
 *
 * The question's maximum mark and granularity live on the question rather than
 * the paper, because AIG-05 requires the mark to be validated against the
 * question and a paper total alone cannot enforce that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('course_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            // EXM-01 fixes the catalogue.
            $table->string('type')->default('end_of_term');
            $table->date('exam_date');
            $table->unsignedSmallInteger('duration_minutes')->nullable();

            // Denormalised so a paper total can be shown without a join, and so
            // MRK-02 can refuse an inconsistent paper at the database level of
            // intent. Recomputed by ExamService when questions change.
            $table->unsignedInteger('total_marks')->default(0);
            $table->unsignedInteger('question_count')->default(0);

            $table->foreignId('grading_scheme_id')->nullable()->constrained()->nullOnDelete();

            // STU-07: markers see a code, not a name, when this is on.
            $table->boolean('blind_marking')->default(false);
            // STU-08: candidates may see their own released result.
            $table->boolean('results_visible_to_students')->default(false);

            // EXM-06 lifecycle. Marking cannot start until the paper is ready.
            $table->string('status')->default('draft');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'status']);
            $table->index('course_unit_id');
        });

        Schema::create('exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('number');
            $table->text('prompt');
            // EXM-02: multiple choice, short answer, numeric, or structured.
            $table->string('kind')->default('short_answer');
            $table->decimal('max_mark', total: 6, places: 2);
            // AIG-05: 1 for whole marks, 0.5 where half marks are allowed.
            $table->decimal('granularity', total: 4, places: 2)->default(1);

            // EXM-03: the marking guide, structured per question so the AI can
            // be measured against it and a teacher can edit one point at a time.
            $table->text('model_answer')->nullable();
            $table->json('guide_points')->nullable();
            /** Options for a multiple choice question, as a JSON array. */
            $table->json('options')->nullable();
            /** Correct options, one letter per key, for objective questions. */
            $table->json('answer_key')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'number']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            // STU-01: unique within the institution, which is why the index is
            // scoped rather than global. Two schools may both have reg no 0042.
            $table->string('reg_no');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('class_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // STU-05: active, deferred, withdrawn, graduated.
            $table->string('status')->default('active');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['institution_id', 'reg_no']);
            $table->index(['institution_id', 'class_name']);
        });

        // STU-03: who sits which exam. A candidate with no enrolment can still
        // be attached to a script, because IDN-06 exists precisely for the
        // candidate whose registration could not be read.
        Schema::create('exam_enrolments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['exam_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_enrolments');
        Schema::dropIfExists('students');
        Schema::dropIfExists('exam_questions');
        Schema::dropIfExists('exams');
    }
};
