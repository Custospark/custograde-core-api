<?php

namespace App\Services\Contracts;

use App\Models\User;

interface AuthServiceInterface
{
    /**
     * Register a personal (no institution) or institutional account.
     * Personal accounts are issued the teacher role with a NULL institution_id;
     * institutional accounts create the institution and issue institution_admin.
     *
     * @param array<string, mixed> $data
     * @return array{user: User, email: string}
     */
    public function register(array $data): array;

    /**
     * False when AUTH_REQUIRE_EMAIL_VERIFICATION is off, in which case
     * registration signs the user in and no code is issued.
     */
    public function requiresEmailVerification(): bool;

    public function login(string $email, string $password): ?User;

    public function loginByEmail(string $email): ?User;

    public function issueVerificationCode(string $email, string $purpose): bool;

    public function verifyCode(string $email, string $purpose, string $code): bool;

    public function authToken(User $user): string;
}
