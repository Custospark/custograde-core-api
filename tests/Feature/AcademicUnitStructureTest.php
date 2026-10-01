<?php

namespace Tests\Feature;

use App\Models\OrgUnit;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Academic hierarchy (ACD-02): the typed org unit tree.
 */
class AcademicUnitStructureTest extends AcademicStructureTestCase
{
    public function test_a_class_cannot_be_placed_at_the_root(): void
    {
        $this->postJson('/api/v1/org-units', [
            'type' => OrgUnit::TYPE_CLASS,
            'name' => 'Senior 4 Blue',
        ], $this->headers)->assertStatus(422);
    }

    public function test_org_unit_hierarchy_rejects_an_impossible_parent(): void
    {
        $facultyId = $this->postJson('/api/v1/org-units', [
            'type' => OrgUnit::TYPE_FACULTY,
            'name' => 'Science Faculty',
        ], $this->headers)->json('id');

        $departmentId = $this->postJson('/api/v1/org-units', [
            'type' => OrgUnit::TYPE_DEPARTMENT,
            'name' => 'Science',
            'parent_id' => $facultyId,
        ], $this->headers)->json('id');

        // A faculty cannot sit under a department, because faculty is above it.
        $this->postJson('/api/v1/org-units', [
            'type' => OrgUnit::TYPE_FACULTY,
            'name' => 'Arts Faculty',
            'parent_id' => $departmentId,
        ], $this->headers)->assertStatus(422);

        // A programme may sit under a department.
        $this->postJson('/api/v1/org-units', [
            'type' => OrgUnit::TYPE_PROGRAMME,
            'name' => 'Natural Sciences',
            'parent_id' => $departmentId,
        ], $this->headers)->assertCreated();
    }

    public function test_a_unit_cannot_be_moved_under_its_own_descendant(): void
    {
        $facultyId = $this->postJson('/api/v1/org-units', [
            'type' => OrgUnit::TYPE_FACULTY,
            'name' => 'Science Faculty',
        ], $this->headers)->json('id');

        $departmentId = $this->postJson('/api/v1/org-units', [
            'type' => OrgUnit::TYPE_DEPARTMENT,
            'name' => 'Physics',
            'parent_id' => $facultyId,
        ], $this->headers)->json('id');

        $programmeId = $this->postJson('/api/v1/org-units', [
            'type' => OrgUnit::TYPE_PROGRAMME,
            'name' => 'Applied Physics',
            'parent_id' => $departmentId,
        ], $this->headers)->json('id');

        // Putting the faculty under its own programme would create a loop.
        $this->postJson("/api/v1/org-units/{$facultyId}/move", [
            'parent_id' => $programmeId,
        ], $this->headers)->assertStatus(422);
    }

    public function test_org_unit_is_tenant_scoped(): void
    {
        $unitId = $this->postJson('/api/v1/org-units', [
            'type' => OrgUnit::TYPE_FACULTY,
            'name' => 'Science Faculty',
        ], $this->headers)->json('id');

        $other = $this->registerSecondInstitution('admin@other.test', 'Hillcrest School');
        $this->getJson("/api/v1/org-units/{$unitId}", $other)->assertNotFound();
    }

    public function test_guests_cannot_reach_org_units(): void
    {
        $this->getJson('/api/v1/org-units')->assertStatus(401);
    }
}
