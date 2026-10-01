<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Personal vs institutional registration (AUT-04, AUT-05, AUT-06).
 *
 * A personal account is an individual teacher with no institution: it creates
 * no institutions row, keeps institution_id NULL and is issued the teacher role.
 * An institutional account creates the institution and is issued
 * institution_admin. Both share POST /api/v1/auth/register and differ only by
 * account_type.
 */
class AccountTypeRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The suite runs with AUTH_REQUIRE_EMAIL_VERIFICATION off. Tests that
     * exercise the code challenge switch it on explicitly.
     */
    protected function enableEmailVerification(): void
    {
        config(['auth.require_email_verification' => true]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => 'grace@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'privacy_consent' => true,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function personalPayload(array $overrides = []): array
    {
        return $this->payload(array_merge([
            'account_type' => User::ACCOUNT_TYPE_PERSONAL,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function institutionalPayload(array $overrides = []): array
    {
        return $this->payload(array_merge([
            'account_type' => User::ACCOUNT_TYPE_INSTITUTIONAL,
            'institution_name' => 'Test Secondary School',
            'institution_type' => 'Secondary School',
        ], $overrides));
    }

    public function test_personal_registration_creates_a_teacher_with_no_institution(): void
    {
        $this->enableEmailVerification();
        Mail::fake();

        $this->postJson('/api/v1/auth/register', $this->personalPayload())
            ->assertCreated()
            ->assertJsonPath('requires_email_verification', true)
            ->assertJsonPath('user.email', 'grace@example.test')
            ->assertJsonPath('user.name', 'Grace Hopper')
            ->assertJsonPath('user.role', 'teacher')
            ->assertJsonPath('user.account_type', 'personal')
            ->assertJsonPath('user.institution_id', null)
            ->assertJsonPath('user.institution', null);

        $this->assertDatabaseHas('users', [
            'email' => 'grace@example.test',
            'role' => 'teacher',
            'account_type' => 'personal',
            'institution_id' => null,
        ]);

        // Self-tenant: no institution row is created at all.
        $this->assertSame(0, Institution::count());
        $this->assertDatabaseHas('verification_codes', ['email' => 'grace@example.test']);
    }

    public function test_personal_registration_does_not_require_institution_fields(): void
    {
        Mail::fake();

        // Explicitly null institution fields must not trip validation.
        $this->postJson('/api/v1/auth/register', $this->personalPayload([
            'institution_name' => null,
            'institution_type' => null,
        ]))->assertCreated();

        $this->assertDatabaseCount('institutions', 0);
    }

    public function test_institutional_registration_requires_institution_fields(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/register', $this->institutionalPayload([
            'institution_name' => null,
        ]))->assertStatus(422)->assertJsonValidationErrors(['institution_name']);

        $this->postJson('/api/v1/auth/register', $this->institutionalPayload([
            'institution_type' => null,
        ]))->assertStatus(422)->assertJsonValidationErrors(['institution_type']);

        $this->assertSame(0, User::count());
        $this->assertSame(0, Institution::count());
    }

    public function test_register_rejects_an_unknown_account_type(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/register', $this->payload([
            'account_type' => 'wizard',
            'institution_name' => 'Test Secondary School',
            'institution_type' => 'Secondary School',
        ]))->assertStatus(422)->assertJsonValidationErrors(['account_type']);

        // Missing account_type is equally invalid.
        $this->postJson('/api/v1/auth/register', $this->payload([
            'institution_name' => 'Test Secondary School',
            'institution_type' => 'Secondary School',
        ]))->assertStatus(422)->assertJsonValidationErrors(['account_type']);

        $this->assertSame(0, User::count());
    }

    public function test_an_unknown_account_type_does_not_create_an_institution(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/register', $this->payload([
            'account_type' => 'Institution',
            'institution_name' => 'Test Secondary School',
            'institution_type' => 'Secondary School',
        ]))->assertStatus(422);

        $this->assertSame(0, Institution::count());
        $this->assertSame(0, User::count());
    }

    public function test_email_is_unique_across_both_account_types(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/register', $this->personalPayload())->assertCreated();

        // The same email cannot be reused for an institutional account.
        $this->postJson('/api/v1/auth/register', $this->institutionalPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertSame(1, User::count());
        $this->assertSame(0, Institution::count());
    }

    public function test_personal_user_can_verify_log_in_and_read_own_profile(): void
    {
        $this->enableEmailVerification();
        Mail::fake();

        $this->postJson('/api/v1/auth/register', $this->personalPayload())->assertCreated();

        // Login is blocked until the email is verified (same gate as institutional).
        $this->postJson('/api/v1/auth/login', [
            'email' => 'grace@example.test',
            'password' => 'password123',
        ])->assertStatus(403)->assertJsonPath('requires_email_verification', true);

        VerificationCode::where('email', 'grace@example.test')->delete();
        VerificationCode::create([
            'email' => 'grace@example.test',
            'purpose' => VerificationCode::PURPOSE_EMAIL_VERIFICATION,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $verify = $this->postJson('/api/v1/auth/verify', [
            'email' => 'grace@example.test',
            'code' => '123456',
            'purpose' => VerificationCode::PURPOSE_EMAIL_VERIFICATION,
        ])->assertOk()->assertJsonPath('user.role', 'teacher');

        $token = $verify->json('token');
        $this->assertNotEmpty($token);

        $headers = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/v1/auth/me', $headers)
            ->assertOk()
            ->assertJsonPath('email', 'grace@example.test')
            ->assertJsonPath('account_type', 'personal')
            ->assertJsonPath('institution', null);

        // The dashboard must tolerate a NULL institution rather than error.
        $this->getJson('/api/v1/dashboard/summary', $headers)
            ->assertOk()
            ->assertJsonPath('institution', null)
            ->assertJsonPath('stats.users', 0);

        $this->postJson('/api/v1/auth/logout', [], $headers)->assertOk();
        Auth::forgetGuards();
        $this->getJson('/api/v1/auth/me', $headers)->assertStatus(401);
    }

    public function test_personal_account_cannot_reach_institution_scoped_records(): void
    {
        $institution = Institution::create([
            'name' => 'Test Secondary School',
            'type' => 'Secondary School',
            'email' => 'admin@test.school',
            'status' => 'active',
        ]);

        $staff = User::factory()->institutional($institution->id)->create([
            'email' => 'staff@test.school',
        ]);
        $personal = User::factory()->personal()->create([
            'email' => 'grace@example.test',
        ]);

        // A personal user has no institution and therefore no membership of one.
        $this->assertNull($personal->institution_id);
        $this->assertTrue($personal->isPersonal());
        $this->assertFalse($staff->isPersonal());
        $this->assertSame(
            [$staff->id],
            $institution->users()->pluck('users.id')->all()
        );
    }

    public function test_register_rejects_missing_consent_and_mismatched_passwords(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/register', $this->personalPayload([
            'privacy_consent' => false,
        ]))->assertStatus(422)->assertJsonValidationErrors(['privacy_consent']);

        $this->postJson('/api/v1/auth/register', $this->personalPayload([
            'password_confirmation' => 'different-password',
        ]))->assertStatus(422)->assertJsonValidationErrors(['password']);

        $this->assertSame(0, User::count());
        $this->assertSame(0, Institution::count());
    }

    public function test_is_personal_defaults_to_false_for_legacy_rows(): void
    {
        $user = User::factory()->institutional()->create();

        $this->assertSame(User::ACCOUNT_TYPE_INSTITUTIONAL, $user->fresh()->account_type);
        $this->assertFalse($user->fresh()->isPersonal());
    }
}
