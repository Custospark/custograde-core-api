<?php

namespace Tests\Feature;

use App\Jobs\ProcessScriptJob;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\GradingScheme;
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

/**
 * Shared setup for the script capture and marking suites.
 *
 * The AI service is faked throughout. These tests are about our rules, not
 * about a model, and a test that depends on a provider fails for reasons
 * nobody can act on. The real provider is exercised by the end-to-end check
 * against a running service, not by the unit suite.
 */
abstract class ScriptMarkingTestCase extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> */
    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Bus::fake();

        $this->bindFakeAi();

        $login = $this->postJson('/api/v1/auth/register', [
            'account_type' => User::ACCOUNT_TYPE_INSTITUTIONAL,
            'first_name' => 'Sarah',
            'last_name' => 'Namono',
            'institution_name' => 'River High School',
            'institution_type' => 'Secondary School',
            'email' => 'sarah@riverhigh.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'privacy_consent' => true,
        ])->assertCreated();

        $this->headers = ['Authorization' => 'Bearer ' . $login->json('token')];
    }

    /**
     * A fake AI that reads three answers, one of which is a graph, so the
     * approval rules and the diagram route both have something realistic.
     */
    private function bindFakeAi(): void
    {
        $this->app->bind(AiServiceInterface::class, fn () => new class implements AiServiceInterface
        {
            public function transcribePage(array $payload): array
            {
                return [
                    'answers' => [
                        [
                            'question_number' => 1,
                            'text' => '3x = 18 therefore x = 6',
                            'content_type' => 'text',
                            'confidence' => 0.95,
                            'low_confidence_words' => [],
                            'truncated' => false,
                            'note' => null,
                        ],
                        [
                            'question_number' => 2,
                            'text' => '(x - 2)(x - 3)',
                            'content_type' => 'text',
                            'confidence' => 0.93,
                            'low_confidence_words' => [],
                            'truncated' => false,
                            'note' => null,
                        ],
                        [
                            // A graph. OCR-07 routes this to a human rather
                            // than asking the model to grade a picture.
                            'question_number' => 3,
                            'text' => 'a graph was drawn',
                            'content_type' => 'non_text',
                            'confidence' => 0.2,
                            'low_confidence_words' => [],
                            'truncated' => false,
                            'note' => 'The candidate drew a graph.',
                        ],
                    ],
                    'warnings' => [],
                    'model_version' => 'fake-vision',
                    'usage' => [['cost_usd' => 0.0005]],
                ];
            }

            public function suggestMarks(array $payload): array
            {
                $proposals = [];
                foreach ($payload['questions'] as $question) {
                    $proposals[] = [
                        'question_number' => $question['question_number'],
                        'suggested_mark' => $question['max_mark'],
                        'confidence' => 0.9,
                        'rationale' => 'All guide points matched.',
                        'points' => [],
                        'strategy_used' => 'semantic',
                        'requires_human' => true,
                        'model_version' => 'fake-grader',
                    ];
                }

                return [
                    'proposals' => $proposals,
                    'failures' => [],
                    'usage' => [['cost_usd' => 0.0007]],
                ];
            }

            public function health(): array
            {
                return ['reachable' => true, 'provider_configured' => true];
            }
        });

        // The pipeline is exercised directly by the tests that need it, so the
        // API tests stay focused on the approval rules.
        $this->app->bind(ScriptPipelineServiceInterface::class, fn () => new class implements ScriptPipelineServiceInterface
        {
            public function process(Script $script): array
            {
                return ['script_id' => $script->id, 'status' => $script->status];
            }
        });
    }

    // --- fixtures -----------------------------------------------------------

    protected function makeExam(array $overrides = []): Exam
    {
        $courseId = $this->postJson('/api/v1/course-units', [
            'code' => 'MATH401',
            'title' => 'Mathematics',
        ], $this->headers)->json('id');

        $examId = $this->postJson('/api/v1/exams', array_merge([
            'title' => 'End of Term Examination',
            'type' => 'end_of_term',
            'course_unit_id' => $courseId,
            'exam_date' => '2026-09-24',
            'status' => 'marking',
        ], $overrides), $this->headers)->json('id');

        $this->postJson("/api/v1/exams/{$examId}/questions", [
            'number' => 1,
            'prompt' => 'Solve for x: 3x + 7 = 25',
            'kind' => 'short_answer',
            'max_mark' => 4,
            'granularity' => 0.5,
            'guide_points' => [
                ['label' => 'Isolates the variable', 'marks' => 2],
                ['label' => 'States the answer', 'marks' => 1],
            ],
        ], $this->headers)->assertCreated();

        $this->postJson("/api/v1/exams/{$examId}/questions", [
            'number' => 2,
            'prompt' => 'Factorise fully: x2 - 5x + 6',
            'kind' => 'short_answer',
            'max_mark' => 4,
            'granularity' => 0.5,
            'guide_points' => [['label' => 'Correct factor pair', 'marks' => 4]],
        ], $this->headers)->assertCreated();

        // A question whose answer is a graph, so the OCR-07 path that routes a
        // diagram to a human rather than grading it from text is exercised.
        $this->postJson("/api/v1/exams/{$examId}/questions", [
            'number' => 3,
            'prompt' => 'Describe the shape of the rainfall graph and give the maximum.',
            'kind' => 'structured',
            'max_mark' => 5,
            'granularity' => 0.5,
            'guide_points' => [
                ['label' => 'Shape identified and named', 'marks' => 2],
                ['label' => 'Maximum read from the graph', 'marks' => 2],
                ['label' => 'Clear explanation', 'marks' => 1],
            ],
        ], $this->headers)->assertCreated();

        return Exam::findOrFail($examId);
    }

    protected function makeStudent(Exam $exam): int
    {
        $studentId = $this->postJson('/api/v1/students', [
            'reg_no' => 'S4B-0041',
            'first_name' => 'Amina',
            'last_name' => 'Nakato',
            'class_name' => 'Senior 4 Blue',
        ], $this->headers)->json('id');

        $this->postJson("/api/v1/exams/{$exam->id}/students", [
            'student_id' => $studentId,
        ], $this->headers)->assertCreated();

        return (int) $studentId;
    }

    protected function uploadScript(Exam $exam, ?int $studentId = null): int
    {
        $response = $this->post("/api/v1/exams/{$exam->id}/scripts", [
            'student_id' => $studentId,
            'file' => UploadedFile::fake()->image('script.png', 1000, 1400),
        ], array_merge($this->headers, ['Accept' => 'application/json']));

        $response->assertCreated();

        return (int) $response->json('script.id');
    }

    /**
     * Run the real pipeline against the fake AI so answers and suggestions exist.
     */
    protected function processScript(int $scriptId): Script
    {
        $pipeline = app(\App\Services\ScriptPipelineService::class);
        $pipeline->process(Script::findOrFail($scriptId));

        return Script::findOrFail($scriptId);
    }

    /**
     * Run the real pipeline against the fake AI so answers and suggestions exist.

    /**
     * Drop the cached guard so the next request is served as the user whose
     * token is supplied, rather than whoever authenticated first in this test.
     *
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    protected function freshGuard(array $headers): array
    {
        Auth::forgetGuards();

        return $headers;
    }

    protected function tokenFor(string $email): string
    {
        return (string) $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ])->assertOk()->json('token');
    }

    /**
     * A contiguous four band scheme, because compiling a result without one is
     * refused by design and a test that needs a graded result has to supply the
     * scheme first rather than discovering that halfway through.
     */
    protected function makeGradingScheme(): GradingScheme
    {
        $this->postJson('/api/v1/grading-schemes', [
            'name' => 'Test Scheme',
            'pass_mark' => 50,
            'bands' => [
                ['grade' => 'A', 'grade_point' => 4, 'min_percent' => 70, 'max_percent' => 100, 'sort_order' => 1],
                ['grade' => 'B', 'grade_point' => 3, 'min_percent' => 60, 'max_percent' => 69.99, 'sort_order' => 2],
                ['grade' => 'C', 'grade_point' => 2, 'min_percent' => 50, 'max_percent' => 59.99, 'sort_order' => 3],
                ['grade' => 'D', 'grade_point' => 1, 'min_percent' => 0, 'max_percent' => 49.99, 'sort_order' => 4],
            ],
        ], $this->headers)->assertCreated();

        return GradingScheme::where('name', 'Test Scheme')->firstOrFail();
    }

    protected function gradingSchemeId(): int
    {
        return (int) GradingScheme::where('name', 'Test Scheme')->value('id');
    }
}
