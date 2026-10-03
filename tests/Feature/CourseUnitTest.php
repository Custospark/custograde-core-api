<?php

namespace Tests\Feature;

use App\Models\CourseResource;
use App\Models\CourseUnit;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Course units and course documents (ACD-04, ACD-02, EXM-03).
 *
 * The course resource tests cover the reference-document upload a teacher uses
 * to attach a past paper or a marking guide. Uploads use postJson rather than
 * post: without an Accept header Laravel treats a validation failure as a form
 * post and redirects with a 302, which is not what an API client sees.
 */
class CourseUnitTest extends AcademicStructureTestCase
{
    // --- ACD-04 course units -------------------------------------------------

    public function test_course_unit_code_is_unique_within_a_tenant_but_not_across_tenants(): void
    {
        $payload = [
            'code' => 'MATH401',
            'title' => 'Mathematics',
            'credit_units' => 4,
        ];

        $this->postJson('/api/v1/course-units', $payload, $this->headers)->assertCreated();

        // Same school, same code, refused.
        $this->postJson('/api/v1/course-units', $payload, $this->headers)->assertStatus(422);

        // A different school may legitimately use the same code.
        $other = $this->registerSecondInstitution('admin@other.test', 'Hillcrest School');
        $this->postJson('/api/v1/course-units', $payload, $other)->assertCreated();
    }

    public function test_course_unit_cannot_reference_another_tenants_org_unit(): void
    {
        $unitId = $this->postJson('/api/v1/org-units', [
            'type' => 'faculty',
            'name' => 'Science Faculty',
        ], $this->headers)->json('id');

        $other = $this->registerSecondInstitution('admin@other.test', 'Hillcrest School');

        // Hillcrest cannot attach a course to River High's faculty.
        $this->postJson('/api/v1/course-units', [
            'code' => 'MATH401',
            'title' => 'Mathematics',
            'org_unit_id' => $unitId,
        ], $other)->assertStatus(422);
    }

    public function test_course_unit_tenant_is_never_taken_from_the_request(): void
    {
        $attackerInstitution = $this->currentUser()->institution_id;

        // A malicious client tries to write a tenant of its own choosing.
        $this->postJson('/api/v1/course-units', [
            'code' => 'HACK001',
            'title' => 'Injected',
            'institution_id' => $attackerInstitution,
            'owner_user_id' => 999,
        ], $this->headers)->assertCreated();

        $course = CourseUnit::where('code', 'HACK001')->firstOrFail();

        // The tenant comes from the token, never from the body.
        $this->assertSame($attackerInstitution, $course->institution_id);
        $this->assertNull($course->owner_user_id);
        $this->assertNotSame(999, $course->owner_user_id);
    }

    public function test_teacher_can_be_attached_and_detached_from_a_course(): void
    {
        $courseId = $this->postJson('/api/v1/course-units', [
            'code' => 'MATH401',
            'title' => 'Mathematics',
        ], $this->headers)->json('id');

        $teacher = $this->createColleague('grace@riverhigh.test', 'Grace Wanjiru');

        $this->postJson("/api/v1/course-units/{$courseId}/teachers", [
            'teacher_id' => $teacher->id,
            'is_responsible' => true,
        ], $this->headers)->assertCreated();

        $this->assertDatabaseHas('course_unit_teachers', [
            'course_unit_id' => $courseId,
            'user_id' => $teacher->id,
            'is_responsible' => true,
        ]);

        $this->deleteJson("/api/v1/course-units/{$courseId}/teachers", [
            'teacher_id' => $teacher->id,
        ], $this->headers)->assertOk();

        $this->assertDatabaseMissing('course_unit_teachers', [
            'course_unit_id' => $courseId,
            'user_id' => $teacher->id,
        ]);
    }

    // --- ACD-02 and EXM-03 course documents ---------------------------------

    public function test_marking_guide_can_be_uploaded_to_a_course(): void
    {
        Storage::fake('local');

        $courseId = $this->postJson('/api/v1/course-units', [
            'code' => 'MATH401',
            'title' => 'Mathematics',
        ], $this->headers)->json('id');

        $this->postJson("/api/v1/course-units/{$courseId}/resources", [
            'title' => 'Term 1 marking guide',
            'kind' => CourseResource::KIND_MARKING_GUIDE,
            'description' => 'Algebra and geometry guide.',
            'file' => UploadedFile::fake()->create('guide.pdf', 120, 'application/pdf'),
        ], $this->headers)->assertCreated()
            ->assertJsonPath('kind', CourseResource::KIND_MARKING_GUIDE)
            ->assertJsonPath('original_name', 'guide.pdf');

        $this->assertDatabaseHas('course_resources', [
            'course_unit_id' => $courseId,
            'kind' => CourseResource::KIND_MARKING_GUIDE,
            'is_current' => true,
        ]);
    }

    public function test_uploaded_resource_never_exposes_a_filesystem_path(): void
    {
        Storage::fake('local');

        $courseId = $this->postJson('/api/v1/course-units', [
            'code' => 'MATH401',
            'title' => 'Mathematics',
        ], $this->headers)->json('id');

        $response = $this->postJson("/api/v1/course-units/{$courseId}/resources", [
            'title' => 'Past paper',
            'kind' => CourseResource::KIND_PAST_PAPER,
            'file' => UploadedFile::fake()->create('paper.pdf', 80, 'application/pdf'),
        ], $this->headers)->assertCreated();

        $body = $response->json();

        $this->assertArrayNotHasKey('path', $body, 'a storage path must never reach the client');
        $this->assertArrayNotHasKey('disk', $body);
    }

    public function test_reuploading_a_guide_supersedes_the_previous_version(): void
    {
        Storage::fake('local');

        $courseId = $this->postJson('/api/v1/course-units', [
            'code' => 'MATH401',
            'title' => 'Mathematics',
        ], $this->headers)->json('id');

        foreach (['guide-v1.pdf', 'guide-v2.pdf'] as $name) {
            $this->postJson("/api/v1/course-units/{$courseId}/resources", [
                'title' => 'Marking guide',
                'kind' => CourseResource::KIND_MARKING_GUIDE,
                'file' => UploadedFile::fake()->create($name, 100, 'application/pdf'),
            ], $this->headers)->assertCreated();
        }

        // EXM-07: an earlier guide is superseded, not overwritten.
        $this->assertSame(2, CourseResource::where('course_unit_id', $courseId)->count());
        $this->assertSame(1, CourseResource::where('course_unit_id', $courseId)->where('is_current', true)->count());
        $this->assertSame(2, CourseResource::where('course_unit_id', $courseId)->max('version'));
    }

        /**
     * Listing a course that is not visible is a 404, not a validation failure.
     *
     * Regression. This shared the upload guard with the read, so a GET answered
     * 422 with "the document was not uploaded", which is not what happened. The
     * courses page hangs on a spinner because of it.
     */
    public function test_listing_resources_for_an_unknown_course_is_not_found(): void
    {
        $response = $this->getJson('/api/v1/course-units/999999/resources', $this->headers)
            ->assertNotFound();

        // A read must never talk about uploading.
        $this->assertStringNotContainsStringIgnoringCase(
            'upload',
            json_encode($response->json()),
            'A GET has nothing to upload, so the message must not mention it.'
        );
    }

    public function test_listing_resources_for_another_schools_course_is_not_found(): void
    {
        $courseId = $this->postJson('/api/v1/course-units', [
            'code' => 'MATH401',
            'title' => 'Mathematics',
        ], $this->headers)->json('id');

        Auth::forgetGuards();

        $other = $this->postJson('/api/v1/auth/register', [
            'account_type' => User::ACCOUNT_TYPE_INSTITUTIONAL,
            'first_name' => 'Other',
            'last_name' => 'School',
            'institution_name' => 'Hillcrest Academy',
            'institution_type' => 'Secondary School',
            'email' => 'other@hillcrest.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'privacy_consent' => true,
        ])->assertCreated();

        // 404 rather than 403, so this cannot be used to discover which course
        // ids exist across the platform.
        $this->getJson("/api/v1/course-units/{$courseId}/resources", [
            'Authorization' => 'Bearer ' . $other->json('token'),
        ])->assertNotFound();
    }

    public function test_listing_resources_for_your_own_course_is_an_empty_list_not_an_error(): void
    {
        $courseId = $this->postJson('/api/v1/course-units', [
            'code' => 'MATH401',
            'title' => 'Mathematics',
        ], $this->headers)->json('id');

        // A course with no documents is empty, not broken. Conflating the two is
        // what makes an empty state look like a failure.
        $response = $this->getJson("/api/v1/course-units/{$courseId}/resources", $this->headers)
            ->assertOk();

        $this->assertSame([], $response->json(), 'A course with no documents returns an empty list');
    }

    public function test_resource_upload_still_reports_a_bad_course_as_a_field_error(): void
    {
        Storage::fake('local');

        // The write path keeps the helpful message, because somebody who picked
        // the wrong course in a form needs to know which field is wrong.
        $this->postJson('/api/v1/course-units/999999/resources', [
            'title' => 'Marking guide',
            'kind' => CourseResource::KIND_MARKING_GUIDE,
            'file' => UploadedFile::fake()->create('guide.pdf', 100, 'application/pdf'),
        ], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['course_unit_id']);
    }

    public function test_resource_upload_rejects_a_disallowed_file_type(): void
    {
        Storage::fake('local');

        $courseId = $this->postJson('/api/v1/course-units', [
            'code' => 'MATH401',
            'title' => 'Mathematics',
        ], $this->headers)->json('id');

        $this->postJson("/api/v1/course-units/{$courseId}/resources", [
            'title' => 'Malware',
            'kind' => CourseResource::KIND_OTHER,
            'file' => UploadedFile::fake()->create('bad.exe', 40, 'application/x-msdownload'),
        ], $this->headers)->assertStatus(422);
    }

    public function test_resource_upload_to_another_tenants_course_is_refused(): void
    {
        Storage::fake('local');

        $courseId = $this->postJson('/api/v1/course-units', [
            'code' => 'MATH401',
            'title' => 'Mathematics',
        ], $this->headers)->json('id');

        $other = $this->registerSecondInstitution('admin@other.test', 'Hillcrest School');

        $this->postJson("/api/v1/course-units/{$courseId}/resources", [
            'title' => 'Injected',
            'kind' => CourseResource::KIND_OTHER,
            'file' => UploadedFile::fake()->create('x.pdf', 40, 'application/pdf'),
        ], $other)->assertStatus(422);

        $this->assertDatabaseMissing('course_resources', ['course_unit_id' => $courseId]);
    }

    public function test_guests_cannot_reach_courses(): void
    {
        $this->getJson('/api/v1/course-units')->assertStatus(401);
        $this->postJson('/api/v1/course-units', [])->assertStatus(401);
    }
}
