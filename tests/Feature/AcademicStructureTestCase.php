<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Shared setup for the academic structure suites (ACD-02 to ACD-05).
 *
 * Every tenant-owned model is two-branch (ADR-002), so most of these tests
 * register more than one tenant and then try to read across the boundary. That
 * makes the guard handling the delicate part, which is why it lives here rather
 * than being repeated per test.
 */
abstract class AcademicStructureTestCase extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> */
    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        // The suite runs with email verification off, so registration returns a
        // working token directly. See phpunit.xml.
        Mail::fake();

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

        $this->headers = ['Authorization' => 'Bearer ' . $login->json('token')];
    }

    protected function currentUser(): User
    {
        return User::where('email', 'sarah@riverhigh.test')->firstOrFail();
    }

    /**
     * A solo teacher. ADR-002 gives them no institution and makes them their own
     * tenant, so their records must never appear in an institution's lists.
     *
     * @return array<string, string>
     */
    protected function registerPersonalUser(string $email): array
    {
        $login = $this->postJson('/api/v1/auth/register', [
            'account_type' => User::ACCOUNT_TYPE_PERSONAL,
            'first_name' => 'Private',
            'last_name' => 'Teacher',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'privacy_consent' => true,
        ])->assertCreated();

        return $this->freshGuard(['Authorization' => 'Bearer ' . $login->json('token')]);
    }

    /**
     * A second, entirely separate school.
     *
     * @return array<string, string>
     */
    protected function registerSecondInstitution(string $email, string $school): array
    {
        $login = $this->postJson('/api/v1/auth/register', [
            'account_type' => User::ACCOUNT_TYPE_INSTITUTIONAL,
            'first_name' => 'Other',
            'last_name' => 'Admin',
            'institution_name' => $school,
            'institution_type' => 'Secondary School',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'privacy_consent' => true,
        ])->assertCreated();

        return $this->freshGuard(['Authorization' => 'Bearer ' . $login->json('token')]);
    }

    /**
     * A second staff member of the SAME school, created directly rather than by
     * registration.
     *
     * Registration always creates a fresh institution, so registering twice with
     * one school name produces two schools rather than a colleague. Staff are
     * invited by an administrator in the real product, which is what this
     * reproduces.
     */
    protected function createColleague(string $email, string $name): User
    {
        $colleague = User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'password123',
            'institution_id' => $this->currentUser()->institution_id,
            'account_type' => User::ACCOUNT_TYPE_INSTITUTIONAL,
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $this->freshGuard($this->headers);

        return $colleague;
    }

    /**
     * Drop the guard instances cached by the in-process test runner before
     * acting as a different user.
     *
     * AuthTest documents the same problem: the first authenticated request in a
     * test resolves and caches the guard, so a later request bearing a different
     * user's token is still served as the first user. That is a test harness
     * artefact, not application behaviour, because a real HTTP client gets a
     * fresh process per request. Every cross-tenant test depends on calling
     * this, otherwise a tenant-isolation test silently runs against the wrong
     * user and proves nothing.
     *
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    protected function freshGuard(array $headers): array
    {
        Auth::forgetGuards();

        return $headers;
    }
}
