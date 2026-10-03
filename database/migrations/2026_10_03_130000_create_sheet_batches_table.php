<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One request to print a whole class of answer sheets (SHT-05).
 *
 * A batch exists as a row because generating thirty sheets takes long enough that
 * holding the request open is not viable, which means a teacher who clicks
 * "print the class" needs somewhere to look to see how far it has got. Without
 * this row the only honest options were blocking the browser or losing the job.
 *
 * The counters are stored rather than derived from the sheets, because "how many
 * failed" is the number an officer needs and counting issued sheets cannot tell
 * them: a candidate who already has a sheet is skipped, and that is neither a
 * success nor a failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sheet_batches', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->index();

            $table->string('status', 20)->default('queued');

            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('failed')->default(0);

            // Where the finished archive lives. Null until the batch completes,
            // because a partially written archive must never be downloadable.
            $table->string('disk', 20)->default('local');
            $table->string('archive_path')->nullable();
            $table->unsignedInteger('archive_bytes')->nullable();

            // Collected per-candidate failures, so an officer learns which
            // candidate did not get a sheet rather than only how many.
            $table->json('errors')->nullable();

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['exam_id', 'status'], 'sheet_batches_exam_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sheet_batches');
    }
};