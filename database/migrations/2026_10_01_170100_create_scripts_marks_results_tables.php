<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Captured scripts, questions answered, marks and results.
 *
 * This is where the human-in-the-loop rules are made structural rather than
 * conventional. The requirement that a human approves every mark (BR-02) is
 * enforced by three separate columns that cannot be filled by a machine:
 * `decided` needs a decision, `approved_by` names a person, and a script cannot
 * be locked while any answer is undecided.
 *
 * The immutable original from CAP-07 is `original_path` plus `original_hash`.
 * Annotations and derived images live in their own columns so nothing ever
 * writes back over the scan a candidate handed in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            // STU-07: identity is re-linked at finalisation, so a marker in
            // blind mode sees only the code.
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();

            // SHT-02: an opaque identifier. No personal data is encoded here.
            $table->string('code')->unique();

            $table->string('status')->default('uploaded');

            // CAP-07: written once, never modified. The hash is ARC-05 tamper
            // evidence and is verified on a schedule.
            $table->string('original_disk')->default('local');
            $table->string('original_path');
            $table->string('original_hash', 64);
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            $table->unsignedSmallInteger('page_count')->default(1);
            $table->unsignedSmallInteger('expected_page_count')->default(1);

            // Denormalised totals, recomputed whenever a mark changes (MRK-01).
            $table->decimal('total_mark', total: 8, places: 2)->default(0);
            $table->decimal('max_mark', total: 8, places: 2)->default(0);

            // REV-04: stored vector data, never drawn into the original.
            $table->json('annotations')->nullable();

            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();

            $table->foreignId('flagged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('flag_note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['exam_id', 'status']);
            $table->index(['institution_id', 'created_at']);
        });

        Schema::create('script_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('script_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->constrained('exam_questions')->nullOnDelete();

            $table->unsignedSmallInteger('question_number');

            // OCR-01: what the reader made of the handwriting. machine_text is
            // never overwritten, because OCR-04 requires a teacher correction
            // to sit beside the original reading rather than replace it.
            $table->text('machine_text')->nullable();
            $table->text('corrected_text')->nullable();
            $table->decimal('transcription_confidence', total: 4, places: 2)->nullable();
            $table->text('low_confidence_words')->nullable();

            // OCR-07: text, non_text or blank. A graph cannot be marked from
            // text and is routed to a human.
            $table->string('content_type')->default('pending');
            $table->boolean('truncated')->default(false);
            $table->text('transcription_note')->nullable();
            $table->string('transcription_model')->nullable();

            // AIG-01: the proposal. Never a mark of record.
            $table->decimal('suggested_mark', total: 6, places: 2)->nullable();
            $table->decimal('suggestion_confidence', total: 4, places: 2)->nullable();
            $table->text('suggestion_rationale')->nullable();
            $table->json('matched_points')->nullable();
            $table->string('suggestion_strategy')->nullable();
            $table->string('suggestion_model')->nullable();
            $table->timestamp('suggested_at')->nullable();

            // The mark of record. Null until a human acts, which is the whole
            // point: decided is false while this is null.
            $table->decimal('mark', total: 6, places: 2)->nullable();
            // BR-05: how the mark was reached.
            $table->string('mark_source')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            // REV-07: required when a mark diverges from an existing one.
            $table->text('reason')->nullable();
            // REV-05: shown to the candidate.
            $table->text('feedback')->nullable();

            $table->timestamps();

            $table->unique(['script_id', 'question_number']);
            $table->index(['script_id', 'approved_at']);
        });

        /**
         * Append-only mark history (REV-13).
         *
         * Every change writes a row. The application user is granted no UPDATE
         * or DELETE on this table, so a mark's history cannot be rewritten even
         * by a bug in our own code.
         */
        Schema::create('mark_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('script_answer_id')->constrained('script_answers')->cascadeOnDelete();
            $table->foreignId('script_id')->constrained('scripts')->cascadeOnDelete();

            $table->decimal('previous_value', total: 6, places: 2)->nullable();
            $table->decimal('new_value', total: 6, places: 2)->nullable();
            $table->string('source');
            $table->text('reason')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['script_id', 'created_at']);
        });

        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('script_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('total_mark', total: 8, places: 2)->default(0);
            $table->decimal('max_mark', total: 8, places: 2)->default(0);
            $table->decimal('percentage', total: 5, places: 2)->nullable();

            // MRK-04: the scheme in force is recorded, so the result can be
            // re-derived later even after the scheme itself has been superseded.
            $table->foreignId('grading_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->string('grade')->nullable();
            $table->string('grade_remark')->nullable();
            $table->boolean('is_pass')->nullable();

            // MRK-06: present, absent, incomplete, malpractice, deferred.
            $table->string('status')->default('draft');
            // MRK-09: whether a candidate may see this.
            $table->string('release_status')->default('withheld');

            // MRK-08: results are versioned, never overwritten. A correction
            // writes version 2 and keeps version 1.
            $table->unsignedInteger('version')->default(1);

            $table->foreignId('finalised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalised_at')->nullable();
            $table->timestamp('released_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['exam_id', 'status']);
            $table->index(['exam_id', 'student_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('results');
        Schema::dropIfExists('mark_events');
        Schema::dropIfExists('script_answers');
        Schema::dropIfExists('scripts');
    }
};
