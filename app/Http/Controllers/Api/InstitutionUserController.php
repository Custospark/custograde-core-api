<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInstitutionUserRequest;
use App\Models\User;
use App\Support\Capability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ValidationException;

/**
 * Adding staff to an institution and changing what they may do (AUT-05, SEC-07).
 *
 * This exists because the capability matrix was unusable without it. Roles could
 * be validated but never assigned, so every account created by registration was an
 * institution administrator, and the honest response was to let administrators do
 * everything rather than leave a school unable to finish an examination.
 *
 * Four rules are enforced here, each of which exists because the alternative is a
 * silent escalation.
 *
 * You cannot grant a role above your own. Authority is ranked, so an
 * administrator may appoint officers and teachers but not another administrator,
 * and nobody can manufacture a role more powerful than the one they hold.
 *
 * `system_admin` is not assignable. It answers to nobody inside an institution, so
 * granting it from inside one would create an account with no accountable owner.
 *
 * You cannot remove the last administrator. An institution with nobody able to
 * manage staff cannot recover, and the only way back in would be a database
 * console.
 *
 * Nobody can change their own role. Otherwise a moderator could promote
 * themselves, which makes the audit trail theatre.
 */
class InstitutionUserController extends Controller
{
    /** Roles that may never be assigned from inside an institution. */
    private const UNASSIGNABLE = [User::ROLE_SYSTEM_ADMIN, User::ROLE_INTEGRATION_CLIENT];

    /** Long enough to be unremarkable in a log, short enough to be typed. */
    private const TEMPORARY_PASSWORD_LENGTH = 16;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $staff = User::query()
            ->where('institution_id', $user->institution_id)
            // The users table stores one `name` column, not first and last.
            // Ordering by last_name here works on SQLite and fails on MySQL, which
            // is why this only showed up against the real database.
            ->orderBy('name')
            ->get()
            ->map(fn (User $member) => $this->present($member, $user));

        return response()->json([
            'users' => $staff,
            'assignable_roles' => $this->assignableRoles(),
        ]);
    }

    /**
     * Add a member of staff with a role, and a password they must change.
     */
    public function store(StoreInstitutionUserRequest $request): JsonResponse
    {
        $actor = $request->user();
        $role = $request->string('role')->toString();

        // A role is only grantable by someone who already holds it. This is the
        // check that stops the endpoint being an escalation path.
        if (! Capability::canGrantRole($actor->role, $role)) {
            return response()->json([
                'message' => 'You cannot give somebody a role that you do not hold yourself.',
                'code' => 'role_not_held',
            ], 403);
        }

        $temporary = $this->temporaryPassword();

        $member = User::create([
            'institution_id' => $actor->institution_id,
            // Registration stores a single name column. Writing first_name and
            // last_name here would be silently dropped by mass assignment, which
            // is how a staff list ends up full of nameless people.
            'name' => trim($request->string('first_name')->toString().' '.$request->string('last_name')->toString()),
            'email' => $request->string('email')->toString(),
            'phone' => $request->string('phone')->toString() ?: null,
            'role' => $role,
            'account_type' => User::ACCOUNT_TYPE_INSTITUTIONAL,
            // Force a change on first sign-in. A password an administrator chose
            // and emailed is a shared secret the day after it is sent.
            'password' => Hash::make($temporary),
            'must_change_password' => true,
            'is_active' => true,
        ]);

        Log::info('Institution staff added', [
            'actor_id' => $actor->id,
            'user_id' => $member->id,
            'role' => $role,
        ]);

        return response()->json([
            'user' => $this->present($member, $actor),
            // Returned once and never stored in readable form, so an administrator
            // can hand the account over without inventing a password in an email.
            'temporary_password' => $temporary,
        ], 201);
    }

    /**
     * Change somebody's role.
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $request->validate([
            'role' => ['required', 'string', 'max:60'],
        ]);

        $role = $request->string('role')->toString();

        if (in_array($role, self::UNASSIGNABLE, true) || ! in_array($role, (new StoreInstitutionUserRequest)->assignableRoles(), true)) {
            return response()->json(['message' => 'That role cannot be assigned here.'], 422);
        }

        $member = $this->findOrRefuse($id, $request);

        if (! $member instanceof User) {
            return $member;
        }

        if ($member->id === $actor->id) {
            // Otherwise a moderator promotes themselves, and the audit trail
            // becomes a record of decisions nobody had to justify.
            return response()->json([
                'message' => 'You cannot change your own role. Ask another administrator.',
                'code' => 'self_role_change',
            ], 409);
        }

        if (! Capability::canGrantRole($actor->role, $role)) {
            return response()->json([
                'message' => 'You cannot give somebody a role that you do not hold yourself.',
                'code' => 'role_not_held',
            ], 403);
        }

        $previous = $member->role;

        // The last administrator guard. An institution with nobody able to manage
        // staff has no way back in short of a database console, so the demotion
        // that would cause it is refused with the reason stated.
        if ($previous === User::ROLE_INSTITUTION_ADMIN && $role !== User::ROLE_INSTITUTION_ADMIN) {
            $remaining = User::query()
                ->where('institution_id', $member->institution_id)
                ->where('role', User::ROLE_INSTITUTION_ADMIN)
                ->where('id', '!=', $member->id)
                ->where('is_active', true)
                ->count();

            if (Capability::wouldLockOutInstitution($previous, $role, $remaining)) {
                throw ValidationException::withMessages([
                    'role' => ['This is the only administrator in the institution. Appoint another one first.'],
                ]);
            }
        }

        $member->update(['role' => $role, 'updated_by' => $actor->id]);

        Log::info('Institution role changed', [
            'actor_id' => $actor->id,
            'user_id' => $member->id,
            'from' => $previous,
            'to' => $role,
        ]);

        return response()->json(['user' => $this->present($member->refresh(), $actor)]);
    }

    /**
     * Deactivate somebody.
     *
     * A deactivate rather than a delete: a mark they approved is part of the
     * record of how that mark came about, and deleting the person would leave an
     * audit trail pointing at nobody (SEC-06).
     */
    public function deactivate(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $member = $this->findOrRefuse($id, $request);

        if (! $member instanceof User) {
            return $member;
        }

        if ($member->id === $actor->id) {
            return response()->json([
                'message' => 'You cannot deactivate your own account.',
                'code' => 'self_deactivation',
            ], 409);
        }

        if ($member->role === User::ROLE_INSTITUTION_ADMIN) {
            $remaining = User::query()
                ->where('institution_id', $member->institution_id)
                ->where('role', User::ROLE_INSTITUTION_ADMIN)
                ->where('id', '!=', $member->id)
                ->where('is_active', true)
                ->count();

            if (Capability::wouldLockOutInstitution(
                $member->role,
                User::ROLE_TEACHER,
                $remaining,
            )) {
                throw ValidationException::withMessages([
                    'id' => ['This is the only administrator in the institution. Appoint another one first.'],
                ]);
            }
        }

        $member->update(['is_active' => false, 'updated_by' => $actor->id]);

        Log::info('Institution staff deactivated', ['actor_id' => $actor->id, 'user_id' => $member->id]);

        return response()->json(['user' => $this->present($member->refresh(), $actor)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $member, User $actor): array
    {
        return [
            'id' => $member->id,
            'full_name' => $member->name,            'email' => $member->email,
            'phone' => $member->phone,
            'role' => $member->role,
            'is_active' => (bool) $member->is_active,
            'must_change_password' => (bool) ($member->must_change_password ?? false),
            'can_change_role' => Capability::canGrantRole($actor->role, $member->role),
            'created_at' => $member->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<string>
     */
    private function assignableRoles(): array
    {
        return (new StoreInstitutionUserRequest)->assignableRoles();
    }

    private function temporaryPassword(): string
    {
        $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $password = '';

        for ($i = 0; $i < self::TEMPORARY_PASSWORD_LENGTH; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        // One character from each class the password rules elsewhere expect, so a
        // generated password can never be rejected by them.
        return 'Cust1'.substr($password, 0, self::TEMPORARY_PASSWORD_LENGTH - 5).'!';
    }

    private function findOrRefuse(int $id, Request $request): User|JsonResponse
    {
        $member = User::query()
            ->where('id', $id)
            ->where('institution_id', $request->user()->institution_id)
            ->first();

        if (! $member instanceof User) {
            return response()->json(['message' => 'We could not find that person.'], 404);
        }

        return $member;
    }
}