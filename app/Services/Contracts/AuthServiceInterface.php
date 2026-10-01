<?php

namespace App\Services\Contracts;

use App\Models\User;

interface AuthServiceInterface
{
    /**
     * @param array<string, mixed> $data
     * @return array{user: User, email: string}
     */
    public function registerInstitution(array $data): array;

    public function login(string $email, string $password): ?User;

    public function loginByEmail(string $email): ?User;

    public function issueVerificationCode(string $email, string $purpose): bool;

    public function verifyCode(string $email, string $purpose, string $code): bool;

    public function authToken(User $user): string;
}
