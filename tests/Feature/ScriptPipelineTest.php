<?php

namespace Tests\Feature;

use App\Jobs\ProcessScriptJob;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\Script;
use App\Models\ScriptAnswer;
use App\Models\User;
use App\Services\Contracts\AiServiceInterface;
use App\Services\Contracts\ScriptPipelineServiceInterface;
use App\Services\ScriptPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

use App\Models\MarkEvent;

/**
 The pipeline proposes and never decides (AIG-01, OCR-01, OCR-07).
 */
class ScriptPipelineTest extends ScriptMarkingTestCase
{
    // --- AIG-01 and OCR: the pipeline proposes, never decides -----------------

    public function test_the_pipeline_writes_suggestions_but_never_a_mark(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);

        $this->processScript($scriptId);

        $answers = ScriptAnswer::where('script_id', $scriptId)->orderBy('question_number')->get();

        $this->assertCount(3, $answers, 'every question on the paper gets a row');

        foreach ($answers as $answer) {
            $this->assertNull(
                $answer->mark,
                'the pipeline must never write a mark, that is the teacher decision'
            );
            $this->assertNull($answer->approved_by);
            $this->assertFalse($answer->isDecided());
        }

        // The two readable answers got proposals.
        $this->assertNotNull($answers[0]->suggested_mark);
        $this->assertNotNull($answers[1]->suggested_mark);

        // The graph was routed to a teacher rather than graded from text.
        $this->assertSame(ScriptAnswer::CONTENT_NON_TEXT, $answers[2]->content_type);
        $this->assertNull($answers[2]->suggested_mark);
    }    // --- REV-01: the human decision ------------------------------------------

    public function test_a_teacher_accepting_a_suggestion_records_the_decision(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);
        $this->processScript($scriptId);

        $answer = ScriptAnswer::where('script_id', $scriptId)->where('question_number', 1)->firstOrFail();

        $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answer->id}/mark", [
            'value' => 4,
            'feedback' => 'Clear working.',
        ], $this->headers)->assertOk()
            ->assertJsonPath('answer.mark', '4.00')
            ->assertJsonPath('answer.mark_source', ScriptAnswer::SOURCE_ACCEPTED)
            ->assertJsonPath('answer.decided', true);

        $answer->refresh();
        $this->assertNotNull($answer->approved_by);
        $this->assertNotNull($answer->approved_at);
        $this->assertSame('Clear working.', $answer->feedback);

        // REV-13: the decision is in the history.
        $this->assertDatabaseHas('mark_events', [
            'script_answer_id' => $answer->id,
            'new_value' => 4,
            'source' => ScriptAnswer::SOURCE_ACCEPTED,
        ]);
    }

    public function test_changing_a_mark_by_more_than_one_point_requires_a_reason(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);
        $this->processScript($scriptId);

        $answer = ScriptAnswer::where('script_id', $scriptId)->where('question_number', 1)->firstOrFail();

        $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answer->id}/mark", [
            'value' => 4,
        ], $this->headers)->assertOk();

        // REV-07: moving from 4 to 1 needs a written reason.
        $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answer->id}/mark", [
            'value' => 1,
        ], $this->headers)->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answer->id}/mark", [
            'value' => 1,
            'reason' => 'Re-read the paper and the final step is not shown.',
        ], $this->headers)->assertOk()
            ->assertJsonPath('answer.mark_source', ScriptAnswer::SOURCE_ADJUSTED);
    }

    public function test_a_mark_beyond_the_question_maximum_is_refused(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);
        $this->processScript($scriptId);

        $answer = ScriptAnswer::where('script_id', $scriptId)->where('question_number', 1)->firstOrFail();

        // The question is out of 4.
        $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answer->id}/mark", [
            'value' => 9,
        ], $this->headers)->assertStatus(422)
            ->assertJsonValidationErrors(['value']);
    }

    public function test_a_mark_that_breaks_the_granularity_is_refused(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);
        $this->processScript($scriptId);

        $answer = ScriptAnswer::where('script_id', $scriptId)->where('question_number', 1)->firstOrFail();

        // The question is marked in half marks, so 1.7 is not a legal mark.
        $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answer->id}/mark", [
            'value' => 1.7,
        ], $this->headers)->assertStatus(422)
            ->assertJsonValidationErrors(['value']);
    }

    public function test_totals_are_computed_not_typed(): void
    {
        $exam = $this->makeExam();
        $studentId = $this->makeStudent($exam);
        $scriptId = $this->uploadScript($exam, $studentId);
        $this->processScript($scriptId);

        $answers = ScriptAnswer::where('script_id', $scriptId)->orderBy('question_number')->get();

        $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answers[0]->id}/mark", [
            'value' => 3,
        ], $this->headers)->assertOk();
        $this->postJson("/api/v1/scripts/{$scriptId}/answers/{$answers[1]->id}/mark", [
            'value' => 4,
        ], $this->headers)->assertOk();

        $script = Script::findOrFail($scriptId);
        // Three questions worth 4, 4 and 5, of which two are decided at 3 and 4.
        $this->assertEquals(7.0, (float) $script->total_mark, 'the total is the sum of decided marks');
        $this->assertEquals(13.0, (float) $script->max_mark, 'the maximum is the sum of question maxima');
    }
}
