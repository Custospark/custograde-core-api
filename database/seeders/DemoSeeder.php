<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\CourseUnit;
use App\Models\GradingScheme;
use App\Models\Institution;
use App\Models\OrgUnit;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo institution, staff and academic structure for local development.
 * Credentials: admin@custograde.test / password123
 *             teacher@custograde.test / password123
 *             grace@custograde.test / password123
 *
 * Everything is created through firstOrCreate keyed on a natural key, so
 * re-seeding is safe and does not duplicate rows.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::firstOrCreate(
            ['email' => 'demo@custograde.test'],
            [
                'name' => 'Demo Secondary School',
                'type' => 'Secondary School',
                'phone' => '+256700000000',
                'status' => 'active',
            ]
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@custograde.test'],
            [
                'name' => 'Demo Admin',
                'password' => 'password123',
                'institution_id' => $institution->id,
                'account_type' => User::ACCOUNT_TYPE_INSTITUTIONAL,
                'role' => User::ROLE_FOR_ACCOUNT_TYPE[User::ACCOUNT_TYPE_INSTITUTIONAL],
                'phone' => '+256700000000',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $institution->update(['owner_id' => $admin->id]);

        $teacher = User::firstOrCreate(
            ['email' => 'teacher@custograde.test'],
            [
                'name' => 'Demo Personal Teacher',
                'password' => 'password123',
                'institution_id' => null,
                'account_type' => User::ACCOUNT_TYPE_PERSONAL,
                'role' => User::ROLE_FOR_ACCOUNT_TYPE[User::ACCOUNT_TYPE_PERSONAL],
                'phone' => '+256700000001',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // A second teacher inside the institution, so the course screen has a
        // colleague to assign rather than an empty list.
        $colleague = User::firstOrCreate(
            ['email' => 'grace@custograde.test'],
            [
                'name' => 'Grace Wanjiru',
                'password' => 'password123',
                'institution_id' => $institution->id,
                'account_type' => User::ACCOUNT_TYPE_INSTITUTIONAL,
                'role' => 'teacher',
                'phone' => '+256700000002',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->seedCalendar($institution, $admin);
        $this->seedStructure($institution, $admin);
        $this->seedCourses($institution, $admin, $teacher, $colleague);
        $this->seedGrading($institution, $admin);
    }

    /**
     * One current year with three terms, Term 1 open. ACD-03.
     */
    private function seedCalendar(Institution $institution, User $admin): void
    {
        $year = AcademicYear::firstOrCreate(
            ['institution_id' => $institution->id, 'name' => '2026'],
            [
                'owner_user_id' => null,
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31',
                'is_current' => true,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]
        );

        $terms = [
            ['name' => 'Term 1', 'sequence' => 1, 'starts_on' => '2026-01-05', 'ends_on' => '2026-04-30', 'status' => Term::STATUS_ACTIVE],
            ['name' => 'Term 2', 'sequence' => 2, 'starts_on' => '2026-05-05', 'ends_on' => '2026-08-31', 'status' => Term::STATUS_PLANNED],
            ['name' => 'Term 3', 'sequence' => 3, 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-20', 'status' => Term::STATUS_PLANNED],
        ];

        foreach ($terms as $term) {
            Term::firstOrCreate(
                ['academic_year_id' => $year->id, 'sequence' => $term['sequence']],
                $term + [
                    'institution_id' => $institution->id,
                    'owner_user_id' => null,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]
            );
        }
    }

    /**
     * A secondary school shape: faculty, department, then two classes. ACD-02.
     */
    private function seedStructure(Institution $institution, User $admin): void
    {
        $faculty = $this->orgUnit($institution, $admin, [
            'parent_id' => null, 'type' => OrgUnit::TYPE_FACULTY, 'name' => 'Science Faculty', 'code' => 'SCI', 'depth' => 0,
        ]);

        $department = $this->orgUnit($institution, $admin, [
            'parent_id' => $faculty->id, 'type' => OrgUnit::TYPE_DEPARTMENT, 'name' => 'Mathematics', 'code' => 'MATH', 'depth' => 1,
        ]);

        $this->orgUnit($institution, $admin, [
            'parent_id' => $department->id, 'type' => OrgUnit::TYPE_CLASS, 'name' => 'Senior 4 Blue', 'code' => 'S4B', 'depth' => 2,
        ]);

        $this->orgUnit($institution, $admin, [
            'parent_id' => $department->id, 'type' => OrgUnit::TYPE_CLASS, 'name' => 'Senior 4 Green', 'code' => 'S4G', 'depth' => 2,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function orgUnit(Institution $institution, User $admin, array $attributes): OrgUnit
    {
        return OrgUnit::firstOrCreate(
            [
                'institution_id' => $institution->id,
                'type' => $attributes['type'],
                'name' => $attributes['name'],
            ],
            $attributes + [
                'owner_user_id' => null,
                'is_active' => true,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]
        );
    }

    /**
     * Two courses. One belongs to the institution, one belongs to the personal
     * teacher, so both ADR-002 branches have visible data after seeding.
     */
    private function seedCourses(Institution $institution, User $admin, User $teacher, User $colleague): void
    {
        $department = OrgUnit::where('institution_id', $institution->id)
            ->where('type', OrgUnit::TYPE_DEPARTMENT)
            ->first();

        $mathematics = CourseUnit::firstOrCreate(
            ['institution_id' => $institution->id, 'code' => 'MATH401'],
            [
                'owner_user_id' => null,
                'org_unit_id' => $department?->id,
                'title' => 'Mathematics',
                'description' => 'Secondary 4 mathematics: algebra, geometry, statistics and trigonometry.',
                'credit_units' => 4,
                'level' => '4',
                'is_active' => true,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]
        );

        $mathematics->teachers()->syncWithoutDetaching([
            $colleague->id => ['is_responsible' => true],
            $admin->id => ['is_responsible' => false],
        ]);

        $english = CourseUnit::firstOrCreate(
            ['institution_id' => $institution->id, 'code' => 'ENG401'],
            [
                'owner_user_id' => null,
                'org_unit_id' => $department?->id,
                'title' => 'English',
                'description' => 'Secondary 4 English: comprehension, composition and grammar.',
                'credit_units' => 3,
                'level' => '4',
                'is_active' => true,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]
        );

        $english->teachers()->syncWithoutDetaching([
            $admin->id => ['is_responsible' => true],
        ]);

        // The personal teacher's own course. No institution_id, and a NULL
        // org_unit because a solo practice has no faculty structure.
        CourseUnit::firstOrCreate(
            ['owner_user_id' => $teacher->id, 'code' => 'P-ENG101'],
            [
                'institution_id' => null,
                'org_unit_id' => null,
                'title' => 'English',
                'description' => 'My own class. Marked without an institution.',
                'credit_units' => 0,
                'level' => null,
                'is_active' => true,
                'created_by' => $teacher->id,
                'updated_by' => $teacher->id,
            ]
        );
    }

    /**
     * A Ugandan-style grading scheme with contiguous bands, marked default.
     * ACD-05.
     */
    private function seedGrading(Institution $institution, User $admin): void
    {
        $scheme = GradingScheme::firstOrCreate(
            ['institution_id' => $institution->id, 'name' => 'Ugandan Secondary'],
            [
                'owner_user_id' => null,
                'description' => 'The standard secondary grading used in Uganda, with A1 through F9 style bands collapsed into four divisions.',
                'pass_mark' => 50,
                'effective_from' => '2026-01-01',
                'is_default' => true,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ]
        );

        if ($scheme->bands()->exists()) {
            return;
        }

        $bands = [
            ['grade' => 'A', 'grade_point' => 4.0, 'remark' => 'Excellent', 'min_percent' => 70, 'max_percent' => 100, 'sort_order' => 1],
            ['grade' => 'B', 'grade_point' => 3.0, 'remark' => 'Very good', 'min_percent' => 60, 'max_percent' => 69.99, 'sort_order' => 2],
            ['grade' => 'C', 'grade_point' => 2.0, 'remark' => 'Good', 'min_percent' => 50, 'max_percent' => 59.99, 'sort_order' => 3],
            ['grade' => 'D', 'grade_point' => 1.0, 'remark' => 'Pass', 'min_percent' => 40, 'max_percent' => 49.99, 'sort_order' => 4],
            ['grade' => 'F', 'grade_point' => 0.0, 'remark' => 'Fail', 'min_percent' => 0, 'max_percent' => 39.99, 'sort_order' => 5],
        ];

        foreach ($bands as $band) {
            $scheme->bands()->create($band);
        }
    }
}
