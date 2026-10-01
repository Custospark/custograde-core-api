<?php

namespace Tests\Feature;

use App\Models\GradingScheme;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Grading schemes and grade bands (ACD-05).
 */
class GradingSchemeTest extends AcademicStructureTestCase
{
    public function test_grading_scheme_rejects_bands_with_a_gap(): void
    {
        $this->postJson('/api/v1/grading-schemes', [
            'name' => 'Ugandan Secondary',
            'pass_mark' => 50,
            'bands' => [
                ['grade' => 'A', 'min_percent' => 70, 'max_percent' => 100],
                ['grade' => 'C', 'min_percent' => 50, 'max_percent' => 60],
                // Nothing covers 60 to 70.
            ],
        ], $this->headers)->assertStatus(422);
    }

    public function test_grading_scheme_rejects_overlapping_bands(): void
    {
        $this->postJson('/api/v1/grading-schemes', [
            'name' => 'Overlap',
            'pass_mark' => 50,
            'bands' => [
                ['grade' => 'A', 'min_percent' => 70, 'max_percent' => 100],
                ['grade' => 'B', 'min_percent' => 60, 'max_percent' => 80],
                ['grade' => 'C', 'min_percent' => 0, 'max_percent' => 59],
            ],
        ], $this->headers)->assertStatus(422);
    }

    public function test_contiguous_grading_scheme_is_accepted_and_grade_for_works(): void
    {
        $this->postJson('/api/v1/grading-schemes', [
            'name' => 'Ugandan Secondary',
            'pass_mark' => 50,
            'bands' => [
                ['grade' => 'A', 'grade_point' => 4, 'min_percent' => 70, 'max_percent' => 100, 'sort_order' => 1],
                ['grade' => 'B', 'grade_point' => 3, 'min_percent' => 60, 'max_percent' => 69.99, 'sort_order' => 2],
                ['grade' => 'C', 'grade_point' => 2, 'min_percent' => 50, 'max_percent' => 59.99, 'sort_order' => 3],
                ['grade' => 'D', 'grade_point' => 1, 'min_percent' => 0, 'max_percent' => 49.99, 'sort_order' => 4],
            ],
        ], $this->headers)->assertCreated();

        $scheme = GradingScheme::where('name', 'Ugandan Secondary')->firstOrFail();
        $scheme->load('bands');

        $this->assertSame('A', $scheme->gradeFor(85)?->grade);
        $this->assertSame('D', $scheme->gradeFor(20)?->grade);
        $this->assertTrue($scheme->isPass(55));
        $this->assertFalse($scheme->isPass(45));
    }

    public function test_grading_scheme_is_tenant_scoped(): void
    {
        $schemeId = $this->postJson('/api/v1/grading-schemes', [
            'name' => 'Private Scheme',
            'pass_mark' => 50,
            'bands' => [
                ['grade' => 'P', 'min_percent' => 50, 'max_percent' => 100],
                ['grade' => 'F', 'min_percent' => 0, 'max_percent' => 49.99],
            ],
        ], $this->headers)->json('id');

        $other = $this->registerSecondInstitution('admin@other.test', 'Hillcrest School');
        $this->getJson("/api/v1/grading-schemes/{$schemeId}", $other)->assertNotFound();
    }

    public function test_guests_cannot_reach_grading_schemes(): void
    {
        $this->getJson('/api/v1/grading-schemes')->assertStatus(401);
        $this->postJson('/api/v1/grading-schemes', [])->assertStatus(401);
    }
}
