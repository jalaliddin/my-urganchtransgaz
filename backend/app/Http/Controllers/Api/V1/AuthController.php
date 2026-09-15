<?php

namespace App\Http\Controllers\Api\V1;

use App\Auth\LoginIdentifierResolver;
use App\Enums\LoginStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\LoginHistory;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function __construct(
        private LoginIdentifierResolver $identifierResolver,
        private AuditLogService $auditLog,
    ) {
        //
    }

    /**
     * Authenticate a user and issue a personal access token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->identifierResolver->resolve($request->string('login')->toString());

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            $this->recordLoginHistory($request, $user, LoginStatus::Failed);

            return $this->error('Login yoki parol noto\'g\'ri.', 401);
        }

        if ($user->status !== UserStatus::Active) {
            $this->recordLoginHistory($request, $user, LoginStatus::Failed);

            return $this->error('Hisobingiz faol emas. Administratorga murojaat qiling.', 403);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->recordLoginHistory($request, $user, LoginStatus::Success);
        $this->auditLog->log('login', 'auth', causer: $user);

        $token = $user->createToken($request->string('device_name')->toString() ?: 'api')->plainTextToken;

        return $this->success([
            'token' => $token,
            'user' => new UserResource($user->load('employee')),
        ], 'Muvaffaqiyatli kirdingiz.');
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->auditLog->log('logout', 'auth', causer: $request->user());

        $request->user()->currentAccessToken()->delete();

        return $this->success(message: 'Tizimdan chiqdingiz.');
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()->load('employee')));
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $request->user()->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
        ])->save();

        $this->auditLog->log('password_changed', 'auth', causer: $request->user());

        return $this->success(message: 'Parol muvaffaqiyatli o\'zgartirildi.');
    }

    /**
     * Send a password reset link to the given email address.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return $this->success(message: 'Agar bu email tizimda mavjud bo\'lsa, parolni tiklash havolasi yuborildi.');
    }

    /**
     * Reset the password using a valid reset token.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->string('password')->toString()),
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error(__($status), 422);
        }

        return $this->success(message: 'Parol muvaffaqiyatli tiklandi.');
    }

    private function recordLoginHistory(LoginRequest $request, ?User $user, LoginStatus $status): void
    {
        LoginHistory::create([
            'user_id' => $user?->id,
            'identifier' => $request->string('login')->toString(),
            'status' => $status,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
