<?php

namespace App\Support;

use App\Models\User;

/**
 * What each role is allowed to do (SEC-07, GOV-01 to GOV-04).
 *
 * Roles exist because separation of duties does. The requirement that matters
 * is SEC-07: a marker shall not approve an appeal on their own script, and a
 * person who generates answer sheets shall not be able to alter finalised marks.
 * Both are impossible while `users.role` is a string nobody reads, which is
 * exactly the state this class replaces.
 *
 * The design rules, in priority order:
 *
 *  1. A capability is granted to a role deliberately. Adding a role must never
 *     silently grant everything, because that is how a scanning operator ends up
 *     able to release results.
 *  2. An unknown role is granted nothing. A typo in a database row must fail
 *     closed, not open. `permissionsFor` returns an empty array for anything not
 *     listed here.
 *  3. Read is separated from write throughout. Seeing a result is not the same
 *     as releasing it, and an auditor needs the first without the second.
 *  4. `system_admin` is the only role that can do everything, because someone
 *     has to be able to recover a tenant that has locked itself out. It is not
 *     assigned by registration and is not reachable from the public API.
 *
 * What this does not yet enforce, so it is not mistaken for more than it is.
 * SEC-07 names two separations. Neither is fully in place:
 *
 *  - "A marker shall not approve an appeal on that marker's own script." Appeals
 *    do not exist (APL-01 to 06 are unbuilt), so there is nothing to gate. When
 *    they land, the rule belongs here as a per-record check rather than a role
 *    capability, because it depends on who marked the row.
 *  - "A person who generates sheets shall not alter finalised marks." Sheet
 *    generation does not exist either (SHT-01 to 09). The capability split is
 *    already in place for when it does: BUILD_ANSWER_SHEETS and
 *    AMEND_APPROVED_MARKS are separate, so one role holding both is visible in
 *    this file rather than buried in a controller.
 *
 * What is enforced today is the part that was quietly missing: a role that has
 * no business releasing results, deciding marks, or reopening approved work
 * cannot do it, and an unrecognised role can do nothing at all.
 */
final class Capability
{
    // Academic structure.
    public const MANAGE_COURSES = 'manage_courses';
    public const MANAGE_RESOURCES = 'manage_resources';
    public const MANAGE_GRADING_SCHEMES = 'manage_grading_schemes';

    // Candidates.
    public const MANAGE_STUDENTS = 'manage_students';

    // Examinations.
    public const MANAGE_EXAMS = 'manage_exams';
    public const BUILD_ANSWER_SHEETS = 'build_answer_sheets';
    public const VIEW_EXAMS = 'view_exams';

    // Capture and identification.
    public const CAPTURE_SCRIPTS = 'capture_scripts';
    public const RESOLVE_IDENTIFICATION = 'resolve_identification';

    // Marking. Split because reading a script is not deciding a mark, and the
    // split is what stops a marker rewriting somebody else's approved work.
    public const VIEW_SCRIPTS = 'view_scripts';
    public const DECIDE_MARKS = 'decide_marks';
    public const AMEND_APPROVED_MARKS = 'amend_approved_marks';
    public const LOCK_SCRIPTS = 'lock_scripts';
    public const FLAG_SCRIPTS = 'flag_scripts';

    // Moderation.
    public const MODERATE = 'moderate';

    // Results.
    public const VIEW_RESULTS = 'view_results';
    public const COMPILE_RESULTS = 'compile_results';
    public const RELEASE_RESULTS = 'release_results';

    // Governance.
    public const VIEW_AUDIT_TRAIL = 'view_audit_trail';
    public const MANAGE_AI_CONFIGURATION = 'manage_ai_configuration';
    public const VIEW_AI_REPORTS = 'view_ai_reports';

    /**
     * Every capability the application knows about. Used by the test suite to
     * prove the matrix below grants nothing that is not declared here, so a
     * capability added to a role but never declared cannot pass unnoticed.
     *
     * @var list<string>
     */
    public const ALL = [
        self::MANAGE_COURSES,
        self::MANAGE_RESOURCES,
        self::MANAGE_GRADING_SCHEMES,
        self::MANAGE_STUDENTS,
        self::MANAGE_EXAMS,
        self::BUILD_ANSWER_SHEETS,
        self::VIEW_EXAMS,
        self::CAPTURE_SCRIPTS,
        self::RESOLVE_IDENTIFICATION,
        self::VIEW_SCRIPTS,
        self::DECIDE_MARKS,
        self::AMEND_APPROVED_MARKS,
        self::LOCK_SCRIPTS,
        self::FLAG_SCRIPTS,
        self::MODERATE,
        self::VIEW_RESULTS,
        self::COMPILE_RESULTS,
        self::RELEASE_RESULTS,
        self::VIEW_AUDIT_TRAIL,
        self::MANAGE_AI_CONFIGURATION,
        self::VIEW_AI_REPORTS,
    ];

    /**
     * The matrix. Read it as the answer to "who may do this, and why".
     *
     * @var array<string, list<string>>
     */
    private const MATRIX = [
        User::ROLE_SYSTEM_ADMIN => self::ALL,

        // Runs the institution, and in a small school is also the teacher. Self
        // registration lands here, so denying the marking path would leave a
        // fresh account unable to finish an examination at all, and there is no
        // user management to grant it a different role yet. Deliberately not
        // granted MODERATE: moderating is meant to be somebody other than the
        // person marking, and that is the separation that survives today.
        User::ROLE_INSTITUTION_ADMIN => [
            self::MANAGE_COURSES,
            self::MANAGE_RESOURCES,
            self::MANAGE_GRADING_SCHEMES,
            self::MANAGE_STUDENTS,
            self::MANAGE_EXAMS,
            self::BUILD_ANSWER_SHEETS,
            self::VIEW_EXAMS,
            self::CAPTURE_SCRIPTS,
            self::VIEW_SCRIPTS,
            self::DECIDE_MARKS,
            self::AMEND_APPROVED_MARKS,
            self::LOCK_SCRIPTS,
            self::FLAG_SCRIPTS,
            self::VIEW_RESULTS,
            self::COMPILE_RESULTS,
            self::RELEASE_RESULTS,
            self::VIEW_AUDIT_TRAIL,
            self::VIEW_AI_REPORTS,
        ],

        // Owns an examination end to end and is accountable for it. This is the
        // one role that both marks and releases, because in a small institution
        // the officer is also the teacher. The audit trail and GOV-03 are what
        // keep that honest, not a second pair of roles.
        User::ROLE_EXAMINATION_OFFICER => [
            self::MANAGE_EXAMS,
            self::BUILD_ANSWER_SHEETS,
            self::VIEW_EXAMS,
            self::CAPTURE_SCRIPTS,
            self::RESOLVE_IDENTIFICATION,
            self::VIEW_SCRIPTS,
            self::DECIDE_MARKS,
            self::AMEND_APPROVED_MARKS,
            self::LOCK_SCRIPTS,
            self::FLAG_SCRIPTS,
            self::MODERATE,
            self::VIEW_RESULTS,
            self::COMPILE_RESULTS,
            self::RELEASE_RESULTS,
            self::VIEW_AUDIT_TRAIL,
            self::VIEW_AI_REPORTS,
        ],

        // The ordinary marking path. Reads and decides marks, locks what they
        // have finished, and cannot amend an approved mark, release a result,
        // or moderate. Asking for an amendment is a request to an officer.
        User::ROLE_TEACHER => [
            self::VIEW_EXAMS,
            self::CAPTURE_SCRIPTS,
            self::VIEW_SCRIPTS,
            self::DECIDE_MARKS,
            self::LOCK_SCRIPTS,
            self::FLAG_SCRIPTS,
            self::VIEW_RESULTS,
            self::VIEW_AI_REPORTS,
        ],

        // Reviews other people's marks. Deliberately cannot decide a mark
        // directly: a moderator who could simply mark would not be moderating,
        // and REV-09 requires their decision to be distinguishable.
        User::ROLE_MODERATOR => [
            self::VIEW_EXAMS,
            self::VIEW_SCRIPTS,
            self::MODERATE,
            self::FLAG_SCRIPTS,
            self::VIEW_RESULTS,
            self::VIEW_AUDIT_TRAIL,
            self::VIEW_AI_REPORTS,
        ],

        // Handles paper. Can scan and can resolve which candidate a page belongs
        // to, and nothing else. Notably cannot view results, so a scanning
        // operator cannot read candidates' marks on the way past.
        User::ROLE_SCANNING_OPERATOR => [
            self::VIEW_EXAMS,
            self::CAPTURE_SCRIPTS,
            self::RESOLVE_IDENTIFICATION,
            self::VIEW_SCRIPTS,
        ],

        // Reads everything, writes nothing. This is the whole point of an audit
        // role, so it deliberately appears nowhere in a write capability.
        User::ROLE_AUDITOR => [
            self::VIEW_EXAMS,
            self::VIEW_SCRIPTS,
            self::VIEW_RESULTS,
            self::VIEW_AUDIT_TRAIL,
            self::VIEW_AI_REPORTS,
        ],

        // Sees only their own results, and that capability is enforced by the
        // ownership check on the result query rather than by this matrix. It is
        // listed so the absence of every other capability is explicit.
        User::ROLE_STUDENT => [],

        // A machine identity. Access is by credential, not by session, and is
        // not routed through this matrix.
        User::ROLE_INTEGRATION_CLIENT => [],
    ];

    /**
     * Capabilities granted to a role. An unrecognised role returns nothing, so a
     * bad value fails closed rather than open.
     *
     * @return list<string>
     */
    public static function permissionsFor(?string $role): array
    {
        if ($role === null) {
            return [];
        }

        return self::MATRIX[$role] ?? [];
    }

    public static function allows(?string $role, string $capability): bool
    {
        return in_array($capability, self::permissionsFor($role), true);
    }

    public static function allowsUser(?User $user, string $capability): bool
    {
        return $user !== null && self::allows($user->role, $capability);
    }

    /**
     * Exposed so the role list and the matrix cannot drift apart. A role with no
     * entry here is a role that would silently grant nothing.
     *
     * @return list<string>
     */
    public static function declaredRoles(): array
    {
        return array_keys(self::MATRIX);
    }
}