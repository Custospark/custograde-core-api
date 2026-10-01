<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'admin_first_name' => 'Ada',
            'admin_last_name' => 'Admin',
            'institution_name' => 'Test Secondary School',
            'institution_type' => 'Secondary School',
            'email' => 'admin@test.school',
            'phone' => '+256700000000',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'privacy_consent' => true,
        ], $overrides);
    }

    protected function registerVerifiedUser(string $email = 'admin@test.school'): User
    {
        Mail::fake();
        $this->postJson('/api/v1/auth/register', $this->registerPayload(['email' => $email]))
            ->assertCreated();

        $code = VerificationCode::where('email', $email)->latest()->first();
        $this->assertNotNull($code);

        // Codes are hashed; recompute a known code for the verify step.
        VerificationCode::where('email', $email)->delete();
        VerificationCode::create([
            'email' => $email,
            'purpose' => VerificationCode::PURPOSE_EMAIL_VERIFICATION,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->postJson('/api/v1/auth/verify', [
            'email' => $email,
            'code' => '123456',
            'purpose' => VerificationCode::PURPOSE_EMAIL_VERIFICATION,
        ])->assertOk();

        return User::where('email', $email)->firstOrFail();
    }

    public function test_register_creates_institution_and_admin_and_requires_verification(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->registerPayload());

        $response->assertCreated()
            ->assertJsonPath('requires_email_verification', true)
            ->assertJsonPath('email', 'admin@test.school')
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'role', 'institution']]);

        $this->assertDatabaseHas('institutions', ['name' => 'Test Secondary School']);
        $this->assertDatabaseHas('users', ['email' => 'admin@test.school', 'role' => 'institution_admin']);
        $this->assertDatabaseHas('verification_codes', ['email' => 'admin@test.school']);
    }

    public function test_register_rejects_duplicate_email_and_missing_consent(): void
    {
        Mail::fake();
        $this->postJson('/api/v1/auth/register', $this->registerPayload())->assertCreated();

        $this->postJson('/api/v1/auth/register', $this->registerPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/v1/auth/register', $this->registerPayload([
            'email' => 'other@test.school',
            'privacy_consent' => false,
        ]))->assertStatus(422)->assertJsonValidationErrors(['privacy_consent']);
    }

    public function test_login_blocked_until_email_verified_then_succeeds(): void
    {
        Mail::fake();
        $this->postJson('/api/v1/auth/register', $this->registerPayload())->assertCreated();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.school',
            'password' => 'password123',
        ])->assertStatus(403)->assertJsonPath('requires_email_verification', true);

        $user = User::where('email', 'admin@test.school')->firstOrFail();
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.school',
            'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['user' => ['id', 'email'], 'token']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.school',
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_verify_with_wrong_code_fails(): void
    {
        Mail::fake();
        $this->postJson('/api/v1/auth/register', $this->registerPayload())->assertCreated();

        $this->postJson('/api/v1/auth/verify', [
            'email' => 'admin@test.school',
            'code' => '000000',
            'purpose' => VerificationCode::PURPOSE_EMAIL_VERIFICATION,
        ])->assertStatus(422);
    }

    public function test_me_logout_and_dashboard_summary(): void
    {
        $this->registerVerifiedUser();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.school',
            'password' => 'password123',
        ])->assertOk();

        $token = $login->json('token');
        $this->assertNotEmpty($token);

        $headers = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/v1/auth/me', $headers)
            ->assertOk()
            ->assertJsonPath('email', 'admin@test.school')
            ->assertJsonPath('institution.name', 'Test Secondary School');

        $this->getJson('/api/v1/dashboard/summary', $headers)
            ->assertOk()
            ->assertJsonStructure(['institution' => ['id', 'name'], 'stats']);

        $this->postJson('/api/v1/auth/logout', [], $headers)->assertOk();

        // Fresh client: drop guard instances cached in-process by the test
        // runner (real HTTP clients get a new process per request).
        Auth::forgetGuards();

        $this->getJson('/api/v1/auth/me', $headers)->assertStatus(401);
    }

    public function test_guests_cannot_reach_protected_routes(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->getJson('/api/v1/dashboard/summary')->assertStatus(401);
    }
}
