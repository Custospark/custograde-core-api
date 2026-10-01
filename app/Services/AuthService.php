<?php

namespace App\Services;

use App\Mail\VerificationCodeMail;
use App\Models\Institution;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\Contracts\AuthServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthService implements AuthServiceInterface
{
    public const CODE_TTL_MINUTES = 15;

    /**
     * Whether new accounts must confirm their email address before signing in.
     */
    public function requiresEmailVerification(): bool
    {
        return (bool) config('auth.require_email_verification', false);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{user: User, email: string}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $isPersonal = $data['account_type'] === User::ACCOUNT_TYPE_PERSONAL;

            $institution = $isPersonal ? null : Institution::create([
                'name' => $data['institution_name'],
                'type' => $data['institution_type'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'status' => 'active',
            ]);

            $user = User::create([
                'name' => trim($data['first_name'].' '.$data['last_name']),
                'email' => $data['email'],
                'password' => $data['password'],
                'institution_id' => $institution?->id,
                'account_type' => $data['account_type'],
                'role' => User::ROLE_FOR_ACCOUNT_TYPE[$data['account_type']],
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]);

            $institution?->update(['owner_id' => $user->id]);

            if ($this->requiresEmailVerification()) {
                $this->sendCode($user->email, VerificationCode::PURPOSE_EMAIL_VERIFICATION);
            }

            return ['user' => $user->load('institution'), 'email' => $user->email];
        });
    }

    public function login(string $email, string $password): ?User
    {
        // Stateless check on purpose: Auth::attempt would open a session,
        // which Sanctum treats as authenticated (TransientToken) even
        // after API tokens are revoked.
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user->load('institution');
    }

    public function loginByEmail(string $email): ?User
    {
        $user = User::where('email', $email)->first();

        return $user?->load('institution');
    }

    public function issueVerificationCode(string $email, string $purpose): bool
    {
        // No code is ever issued while verification is switched off, so the
        // endpoint must not appear to succeed.
        if ($purpose === VerificationCode::PURPOSE_EMAIL_VERIFICATION && ! $this->requiresEmailVerification()) {
            return false;
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            return false;
        }

        if ($purpose === VerificationCode::PURPOSE_EMAIL_VERIFICATION && $user->email_verified_at) {
            return false;
        }

        $this->sendCode($email, $purpose);

        return true;
    }

    public function verifyCode(string $email, string $purpose, string $code): bool
    {
        $record = VerificationCode::where('email', $email)
            ->where('purpose', $purpose)
            ->latest()
            ->first();

        if (! $record || $record->isExpired()) {
            return false;
        }

        if (! Hash::check($code, $record->code_hash)) {
            return false;
        }

        VerificationCode::where('email', $email)->where('purpose', $purpose)->delete();

        if ($purpose === VerificationCode::PURPOSE_EMAIL_VERIFICATION) {
            User::where('email', $email)->update(['email_verified_at' => now()]);
        }

        return true;
    }

    public function authToken(User $user): string
    {
        return $user->createToken('auth-token')->plainTextToken;
    }

    protected function sendCode(string $email, string $purpose): void
    {
        $code = (string) random_int(100000, 999999);

        VerificationCode::where('email', $email)->where('purpose', $purpose)->delete();

        VerificationCode::create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ]);

        Mail::to($email)->send(new VerificationCodeMail($code, $purpose));
    }
}
