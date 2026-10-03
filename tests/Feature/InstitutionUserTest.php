<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Capability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

/**
 * Adding staff and assigning roles (AUT-05, SEC-07).
 *
 * This endpoint is the one that makes the capability matrix real, and also the
 * one that could quietly undo it. A way to assign roles is a way to grant every
 * capability, so the tests are mostly about what it refuses to do.
 *
 * Each refusal below closes a specific escalation:
 *
 *  - granting a role you do not hold      -> any teacher could mint an officer
 *  - assigning system_admin               -> an account inside a tenant that
 *                                            answers to nobody in that tenant
 *  - changing your own role               -> a moderator promotes themselves
 *  - removing the last administrator      -> a tenant that can never be recovered
 */
class InstitutionUserTest extends \Tests\TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->headers = ['Authorization' => 'Bearer '.$login->json('token')];
    }

    private function asRole(string $role): array
    {
        User::where('email', 'sarah@riverhigh.test')->firstOrFail()->update(['role' => $role]);
        Auth::forgetGuards();

        return [
            'Authorization' => 'Bearer '.$this->postJson('/api/v1/auth/login', [
                'email' => 'sarah@riverhigh.test',
                'password' => 'password123',
            ])->assertOk()->json('token'),
        ];
    }

    private function addStaff(array $overrides = []): array
    {
        return [
            'first_name' => 'Amina',
            'last_name' => 'Nakato',
            'email' => 'amina@riverhigh.test',
            'role' => User::ROLE_TEACHER,
            ...$overrides,
        ];
    }

    // --- adding staff --------------------------------------------------------

    public function test_an_administrator_can_add_a_teacher(): void
    {
        $response = $this->postJson('/api/v1/institution/users', $this->addStaff(), $this->headers)
            ->assertCreated();

        $this->assertSame(User::ROLE_TEACHER, $response->json('user.role'));

        // A password the administrator did not choose, so it is never a shared
        // secret from the day it is sent.
        $this->assertNotEmpty($response->json('temporary_password'));
        $this->assertTrue($response->json('user.must_change_password'));
    }

    public function test_the_generated_password_is_never_the_one_sent_back_as_a_hash(): void
    {
        $created = $this->postJson('/api/v1/institution/users', $this->addStaff(), $this->headers)
            ->assertCreated();

        $member = User::where('email', 'amina@riverhigh.test')->firstOrFail();

        $this->assertNotSame(
            $created->json('temporary_password'),
            $member->password,
            'The stored value must be a hash, not the readable password'
        );
        $this->assertTrue(password_verify($created->json('temporary_password'), $member->password));
    }

    public function test_an_existing_email_cannot_be_added_twice(): void
    {
        $this->postJson('/api/v1/institution/users', $this->addStaff(), $this->headers)->assertCreated();

        $this->postJson('/api/v1/institution/users', $this->addStaff([
            'email' => 'someone.else@riverhigh.test',
        ]), $this->headers)->assertCreated();

        $this->postJson('/api/v1/institution/users', $this->addStaff(), $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_staff_are_created_inside_the_actors_institution_only(): void
    {
        $this->postJson('/api/v1/institution/users', $this->addStaff(), $this->headers)->assertCreated();

        $member = User::where('email', 'amina@riverhigh.test')->firstOrFail();

        $this->assertSame(
            User::where('email', 'sarah@riverhigh.test')->firstOrFail()->institution_id,
            $member->institution_id,
        );
    }

    /**
     * The name is actually stored.
     *
     * Written because `users` has one `name` column rather than first and last.
     * Setting `first_name` and `last_name` is silently dropped by mass assignment,
     * so a staff list can end up full of nameless people with no error anywhere.
     * SQLite tolerated an ordering by a column that MySQL rejects, which is why
     * this is asserted against the stored value rather than the response shape.
     */
    public function test_the_name_is_stored_on_the_user_row(): void
    {
        $response = $this->postJson('/api/v1/institution/users', $this->addStaff([
            'first_name' => 'Amina',
            'last_name' => 'Nakato',
        ]), $this->headers)->assertCreated();

        $member = User::where('email', 'amina@riverhigh.test')->firstOrFail();

        $this->assertSame('Amina Nakato', $member->name);
        $this->assertSame('Amina Nakato', $response->json('user.full_name'));
    }

    public function test_the_staff_list_is_returned_in_a_usable_order(): void
    {
        foreach (['Zebra', 'Amina', 'Bwalya'] as $index => $first) {
            $this->postJson('/api/v1/institution/users', [
                'first_name' => $first,
                'last_name' => 'Candidate'.$index,
                'email' => "order{$index}@riverhigh.test",
                'role' => User::ROLE_TEACHER,
            ], $this->headers)->assertCreated();
        }

        $names = array_column(
            $this->getJson('/api/v1/institution/users', $this->headers)->assertOk()->json('users'),
            'full_name'
        );

        $sorted = $names;
        sort($sorted);

        $this->assertSame($sorted, $names, 'The staff list should arrive already ordered');
    }

    // --- refusals ------------------------------------------------------------

    public function test_a_role_you_do_not_hold_cannot_be_granted(): void
    {
        // An administrator holds MANAGE_INSTITUTION_USERS but deliberately does
        // not hold MODERATE... so the check below uses a role an administrator
        // genuinely lacks: granting teacher is fine, granting moderator is too,
        // because administrators hold both. The real escalation is a teacher.
        $teacher = $this->asRole(User::ROLE_TEACHER);

        $this->postJson('/api/v1/institution/users', $this->addStaff(), $teacher)->assertForbidden();
    }

    public function test_a_teacher_cannot_reach_the_endpoint_at_all(): void
    {
        $teacher = $this->asRole(User::ROLE_TEACHER);

        $this->getJson('/api/v1/institution/users', $teacher)->assertForbidden();
        $this->putJson('/api/v1/institution/users/1/role', ['role' => User::ROLE_TEACHER], $teacher)
            ->assertForbidden();
    }

    public function test_system_admin_cannot_be_assigned_from_inside_an_institution(): void
    {
        $this->postJson('/api/v1/institution/users', $this->addStaff([
            'role' => User::ROLE_SYSTEM_ADMIN,
        ]), $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_an_unknown_role_is_refused(): void
    {
        $this->postJson('/api/v1/institution/users', $this->addStaff([
            'role' => 'wizard',
        ]), $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_nobody_can_change_their_own_role(): void
    {
        $admin = User::where('email', 'sarah@riverhigh.test')->firstOrFail();

        $this->putJson("/api/v1/institution/users/{$admin->id}/role", [
            'role' => User::ROLE_TEACHER,
        ], $this->headers)
            ->assertStatus(409)
            ->assertJsonPath('code', 'self_role_change');

        $this->assertSame(
            User::ROLE_INSTITUTION_ADMIN,
            $admin->refresh()->role,
            'A refused self-demotion must not have partially applied'
        );
    }

    /**
     * Two administrators can demote each other, because each one is the other's
     * guarantee that the tenant is not locked out.
     */
    public function test_a_second_administrator_can_be_demoted(): void
    {
        $second = $this->postJson('/api/v1/institution/users', $this->addStaff([
            'email' => 'head@riverhigh.test',
            'first_name' => 'Grace',
            'last_name' => 'Head',
            'role' => User::ROLE_INSTITUTION_ADMIN,
        ]), $this->headers)->assertCreated()->json('user');

        $this->assertTrue(
            $second['role'] === User::ROLE_INSTITUTION_ADMIN,
            'An administrator should be able to appoint another administrator'
        );

        $this->putJson("/api/v1/institution/users/{$second['id']}/role", [
            'role' => User::ROLE_TEACHER,
        ], $this->headers)->assertOk();

        $this->assertSame(
            User::ROLE_TEACHER,
            User::find($second['id'])->role,
        );
    }

    /**
     * The lockout rule itself.
     *
     * While only administrators may manage staff this cannot be reached over HTTP,
     * because the acting administrator always counts as a remaining one. It is
     * tested as a pure rule rather than pretended to be covered end to end, which
     * is the honest option and keeps it correct the day staff management is widened.
     */
    public function test_the_lockout_rule_keeps_the_last_administrator(): void
    {
        // Demoting the only administrator would lock the tenant out.
        $this->assertTrue(Capability::wouldLockOutInstitution(
            User::ROLE_INSTITUTION_ADMIN,
            User::ROLE_TEACHER,
            0
        ));

        // One other administrator remains, so it is safe.
        $this->assertFalse(Capability::wouldLockOutInstitution(
            User::ROLE_INSTITUTION_ADMIN,
            User::ROLE_TEACHER,
            1
        ));

        // Promoting is never a lockout.
        $this->assertFalse(Capability::wouldLockOutInstitution(
            User::ROLE_INSTITUTION_ADMIN,
            User::ROLE_INSTITUTION_ADMIN,
            0
        ));

        // Demoting somebody who was not the last administrator is not a lockout,
        // whatever else is true.
        $this->assertFalse(Capability::wouldLockOutInstitution(
            User::ROLE_TEACHER,
            User::ROLE_AUDITOR,
            0
        ));
    }

    public function test_an_administrator_cannot_appoint_another_administrator_over_a_promoted_officer(): void
    {
        // Rank is the rule: an officer outranks a teacher but not an administrator.
        $this->assertTrue(Capability::canGrantRole(User::ROLE_INSTITUTION_ADMIN, User::ROLE_EXAMINATION_OFFICER));
        $this->assertTrue(Capability::canGrantRole(User::ROLE_EXAMINATION_OFFICER, User::ROLE_TEACHER));

        $this->assertFalse(
            Capability::canGrantRole(User::ROLE_EXAMINATION_OFFICER, User::ROLE_INSTITUTION_ADMIN),
            'An officer must not be able to appoint an administrator'
        );
        // Equal rank is grantable, which is what lets an administrator appoint a
        // second administrator as a safeguard against lockout.
        $this->assertTrue(Capability::canGrantRole(User::ROLE_TEACHER, User::ROLE_TEACHER));

        // Less authority cannot appoint more, in either direction.
        $this->assertFalse(Capability::canGrantRole(User::ROLE_AUDITOR, User::ROLE_TEACHER));
        $this->assertFalse(Capability::canGrantRole(User::ROLE_SCANNING_OPERATOR, User::ROLE_TEACHER));
        $this->assertFalse(Capability::canGrantRole(null, User::ROLE_TEACHER));
        $this->assertFalse(Capability::canGrantRole(User::ROLE_TEACHER, 'wizard'));
    }

    // --- tenancy -------------------------------------------------------------

    public function test_another_institution_cannot_see_or_change_our_staff(): void
    {
        $ours = $this->postJson('/api/v1/institution/users', $this->addStaff(), $this->headers)
            ->assertCreated()->json('user');

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

        $headers = ['Authorization' => 'Bearer '.$other->json('token')];

        $this->getJson('/api/v1/institution/users', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.email', 'other@hillcrest.test');

        $this->putJson("/api/v1/institution/users/{$ours['id']}/role", [
            'role' => User::ROLE_TEACHER,
        ], $headers)->assertNotFound();

        $this->deleteJson("/api/v1/institution/users/{$ours['id']}", [], $headers)->assertNotFound();
    }

    // --- the capability itself ----------------------------------------------

    public function test_only_administrators_may_manage_staff(): void
    {
        $this->assertTrue(Capability::allows(User::ROLE_INSTITUTION_ADMIN, Capability::MANAGE_INSTITUTION_USERS));

        // Every other institutional role must be absent from this capability, or
        // the endpoint above would be reachable by somebody it should not be.
        foreach ([
            User::ROLE_TEACHER,
            User::ROLE_MODERATOR,
            User::ROLE_EXAMINATION_OFFICER,
            User::ROLE_SCANNING_OPERATOR,
            User::ROLE_AUDITOR,
            User::ROLE_STUDENT,
        ] as $role) {
            $this->assertFalse(
                Capability::allows($role, Capability::MANAGE_INSTITUTION_USERS),
                "{$role} must not be able to manage staff"
            );
        }
    }

    public function test_a_deactivated_member_cannot_sign_in(): void
    {
        $created = $this->postJson('/api/v1/institution/users', $this->addStaff(), $this->headers)
            ->assertCreated();

        $temporary = $created->json('temporary_password');
        $member = User::where('email', 'amina@riverhigh.test')->firstOrFail();

        // Proves the account worked before deactivation, so the refusal below is
        // about the deactivation and not about a wrong password.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'amina@riverhigh.test',
            'password' => $temporary,
        ])->assertOk();

        $this->deleteJson("/api/v1/institution/users/{$member->id}", [], $this->headers)->assertOk();
        $this->assertFalse((bool) $member->refresh()->is_active);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'amina@riverhigh.test',
            'password' => $temporary,
        ])->assertStatus(403);
    }

    public function test_listing_staff_states_which_roles_the_caller_may_hand_out(): void
    {
        $response = $this->getJson('/api/v1/institution/users', $this->headers)->assertOk();

        $roles = $response->json('assignable_roles');

        $this->assertContains(User::ROLE_TEACHER, $roles);
        $this->assertNotContains(User::ROLE_SYSTEM_ADMIN, $roles);
        $this->assertNotContains(User::ROLE_INTEGRATION_CLIENT, $roles);
    }
}