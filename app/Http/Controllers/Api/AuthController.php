<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterInstitutionRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SendVerificationCodeRequest;
use App\Http\Requests\VerifyCodeRequest;
use App\Http\Resources\UserResource;
use App\Models\VerificationCode;
use App\Services\Contracts\AuthServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function __construct(
        protected AuthServiceInterface $authService,
    ) {}

    public function register(RegisterInstitutionRequest $request): JsonResponse
    {
        $result = $this->authService->registerInstitution($request->validated());

        return response()->json([
            'user' => new UserResource($result['user']),
            'requires_email_verification' => true,
            'email' => $result['email'],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->login($request->email, $request->password);

        if (! $user) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Your account has been deactivated.'], 403);
        }

        if (! $user->email_verified_at) {
            $this->authService->issueVerificationCode(
                $user->email,
                VerificationCode::PURPOSE_EMAIL_VERIFICATION
            );

            return response()->json([
                'message' => 'Please verify your email address to continue.',
                'requires_email_verification' => true,
                'email' => $user->email,
            ], 403);
        }

        return response()->json([
            'user' => new UserResource($user),
            'token' => $this->authService->authToken($user),
        ]);
    }

    public function sendVerificationCode(SendVerificationCodeRequest $request): JsonResponse
    {
        $sent = $this->authService->issueVerificationCode($request->email, $request->purpose);

        if (! $sent) {
            return response()->json(['message' => 'Invalid credentials'], 404);
        }

        return response()->json(['message' => 'If that email address is associated with an account, a security code has been sent.']);
    }

    public function verify(VerifyCodeRequest $request): JsonResponse
    {
        $ok = $this->authService->verifyCode($request->email, $request->purpose, $request->code);

        if (! $ok) {
            return response()->json(['message' => 'That security code is invalid or has expired.'], 422);
        }

        $user = $this->authService->loginByEmail($request->email);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $this->authService->authToken($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('institution'));
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'If that email address is associated with an account, a password reset link has been sent.'])
            : response()->json(['message' => 'Unable to send password reset link.'], 500);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = $password;
                $user->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password has been reset successfully.'])
            : response()->json(['message' => 'Invalid or expired reset token.'], 400);
    }
}
