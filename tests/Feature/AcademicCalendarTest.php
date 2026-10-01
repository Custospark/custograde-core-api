<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Term;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Academic calendar (ACD-03): academic years and terms.
 */
class AcademicCalendarTest extends AcademicStructureTestCase
{
    public function test_academic_year_can_be_created_and_listed(): void
    {
        $this->postJson('/api/v1/academic-years', [
            'name' => '2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ], $this->headers)->assertCreated()
            ->assertJsonPath('name', '2026');

        // The list is paginated, so the envelope carries the page and the rows
        // are the plain array alongside it.
        $this->getJson('/api/v1/academic-years', $this->headers)
            ->assertOk()
            ->assertJsonPath('years.0.name', '2026')
            ->assertJsonPath('meta.total', 1);

        $this->assertDatabaseHas('academic_years', [
            'name' => '2026',
            'institution_id' => $this->currentUser()->institution_id,
        ]);
    }

    public function test_academic_year_is_scoped_to_its_tenant(): void
    {
        $this->postJson('/api/v1/academic-years', [
            'name' => '2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ], $this->headers)->assertCreated();

        $yearId = AcademicYear::where('name', '2026')->firstOrFail()->id;
        $otherSchool = $this->registerSecondInstitution('admin@other.test', 'Hillcrest School');

        $this->getJson('/api/v1/academic-years', $otherSchool)
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->getJson("/api/v1/academic-years/{$yearId}", $otherSchool)->assertNotFound();
    }

    public function test_personal_account_owns_its_own_academic_year(): void
    {
        $personal = $this->registerPersonalUser('solo@custograde.test');
        $user = User::where('email', 'solo@custograde.test')->firstOrFail();

        $this->postJson('/api/v1/academic-years', [
            'name' => '2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ], $personal)->assertCreated();

        // ADR-002: a personal account has no institution and is its own tenant.
        $this->assertDatabaseHas('academic_years', [
            'name' => '2026',
            'institution_id' => null,
            'owner_user_id' => $user->id,
        ]);

        // The institution tenant must not see the solo teacher's year. The
        // guard is dropped first so the request is served as the institutional
        // user rather than the personal one who registered most recently.
        $this->getJson('/api/v1/academic-years', $this->freshGuard($this->headers))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_term_must_sit_inside_its_academic_year(): void
    {
        $yearId = $this->postJson('/api/v1/academic-years', [
            'name' => '2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ], $this->headers)->json('id');

        $this->postJson('/api/v1/terms', [
            'academic_year_id' => $yearId,
            'name' => 'Term 1',
            'sequence' => 1,
            'starts_on' => '2026-01-15',
            'ends_on' => '2026-04-30',
        ], $this->headers)->assertCreated();

        // Term 5 sits outside the year, so it is refused with a readable reason.
        $this->postJson('/api/v1/terms', [
            'academic_year_id' => $yearId,
            'name' => 'Term 5',
            'sequence' => 5,
            'starts_on' => '2027-01-10',
            'ends_on' => '2027-04-30',
        ], $this->headers)->assertStatus(422);
    }

    public function test_only_one_term_can_be_active_per_year(): void
    {
        $yearId = $this->postJson('/api/v1/academic-years', [
            'name' => '2026',
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ], $this->headers)->json('id');

        $first = null;
        $second = null;

        foreach ([1, 2] as $sequence) {
            $response = $this->postJson('/api/v1/terms', [
                'academic_year_id' => $yearId,
                'name' => "Term {$sequence}",
                'sequence' => $sequence,
                'starts_on' => '2026-01-05',
                'ends_on' => '2026-04-30',
            ], $this->headers)->assertCreated();

            if ($sequence === 1) {
                $first = $response->json('id');
            } else {
                $second = $response->json('id');
            }
        }

        $this->postJson("/api/v1/terms/{$first}/activate", [], $this->headers)->assertOk();
        $this->postJson("/api/v1/terms/{$second}/activate", [], $this->headers)->assertOk();

        // Opening the second term closed the first, so exactly one is active.
        $this->assertSame(1, Term::where('status', Term::STATUS_ACTIVE)->count());
        $this->assertSame(Term::STATUS_CLOSED, Term::findOrFail($first)->status);
    }

    public function test_guests_cannot_reach_the_calendar(): void
    {
        $this->getJson('/api/v1/academic-years')->assertStatus(401);
        $this->getJson('/api/v1/terms')->assertStatus(401);
    }
}
