<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * AUTH_REQUIRE_EMAIL_VERIFICATION = false (the current .env default).
 *
 * Registration must hand back a usable token, issue no code, and sign the
 * user in without a round trip to the verification screen. The endpoints stay
 * registered so the flag can be flipped back on without a redeploy.
 */
class EmailVerificationDisabledTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'account_type' => User::ACCOUNT_TYPE_INSTITUTIONAL,
            'first_name' => 'Ada',
            'last_name' => 'Admin',
            'institution_name' => 'Test Secondary School',
            'institution_type' => 'Secondary School',
            'email' => 'admin@test.school',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'privacy_consent' => true,
        ], $overrides);
    }

    public function test_the_flag_is_off_in_the_test_environment(): void
    {
        $this->assertFalse((bool) config('auth.require_email_verification'));
    }

    public function test_register_returns_a_working_token_and_issues_no_code(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->payload())
            ->assertCreated()
            ->assertJsonPath('requires_email_verification', false)
            ->assertJsonPath('user.role', 'institution_admin');

        $token = $response->json('token');
        $this->assertNotEmpty($token);

        // No code was minted, so nothing was mailed either.
        $this->assertSame(0, VerificationCode::count());
        Mail::assertNothingSent();

        // The token authenticates straight away.
        $headers = ['Authorization' => "Bearer {$token}"];
        $this->getJson('/api/v1/auth/me', $headers)
            ->assertOk()
            ->assertJsonPath('email', 'admin@test.school');
    }

    public function test_personal_register_returns_a_working_token_without_an_institution(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->payload([
            'account_type' => User::ACCOUNT_TYPE_PERSONAL,
            'email' => 'grace@example.test',
        ]))
            ->assertCreated()
            ->assertJsonPath('requires_email_verification', false)
            ->assertJsonPath('user.role', 'teacher')
            ->assertJsonPath('user.account_type', 'personal');

        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $this->assertSame(0, Institution::count());
        $this->assertSame(0, VerificationCode::count());

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('institution', null);
    }

    public function test_login_succeeds_without_a_verified_email(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated();

        $user = User::where('email', 'admin@test.school')->firstOrFail();
        $this->assertNull($user->email_verified_at);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.school',
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['user' => ['id', 'email'], 'token']);
    }

    public function test_requesting_a_code_is_refused_while_the_flag_is_off(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated();

        $this->postJson('/api/v1/auth/verify/send', [
            'email' => 'admin@test.school',
            'purpose' => VerificationCode::PURPOSE_EMAIL_VERIFICATION,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Email verification is not enabled on this installation.');

        $this->assertSame(0, VerificationCode::count());
    }

    public function test_the_inactive_account_guard_still_applies(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated();

        User::where('email', 'admin@test.school')->update(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.school',
            'password' => 'password123',
        ])->assertStatus(403)
            ->assertJsonPath('message', 'Your account has been deactivated.');
    }
}
