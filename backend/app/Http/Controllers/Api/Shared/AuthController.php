<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\RuntimeConfiguration;
use App\Support\ActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Handles employee-ID authentication, session lifecycle, and security events.
 */
class AuthController extends Controller
{
    private const DUMMY_PASSWORD_HASH = '$2y$12$/kL5pqfp1TVj3gQg9KQz/OFCMsDTf.AftokEb/KldEWQhfSep2HjO';

    public function __construct(private readonly RuntimeConfiguration $configuration) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $authentication = DB::transaction(function () use ($credentials): array {
            $user = User::query()
                ->with(['office', 'role.permissions', 'roles.permissions'])
                ->where('employee_id', $credentials['employeeId'])
                ->lockForUpdate()
                ->first();

            if (! $user) {
                return ['status' => 'invalid', 'user' => null];
            }

            if ($user->isLocked()) {
                return ['status' => 'locked', 'user' => $user];
            }

            $previousLockExpired = $user->locked_until !== null;
            $passwordIsValid = $user->is_active
                && $user->effectiveRoles()->isNotEmpty()
                && Hash::check($credentials['password'], $user->password);

            if (! $passwordIsValid) {
                $attempts = ($previousLockExpired ? 0 : $user->failed_login_attempts) + 1;

                $user->forceFill([
                    'failed_login_attempts' => $attempts,
                    'locked_until' => $attempts >= $this->configuration->failedLoginLimit()
                        ? now()->addMinutes($this->configuration->accountLockMinutes())
                        : null,
                ])->save();

                return ['status' => 'invalid', 'user' => $user];
            }

            $user->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'last_login_at' => now(),
            ])->save();

            return ['status' => 'authenticated', 'user' => $user];
        }, 3);

        /** @var User|null $user */
        $user = $authentication['user'];

        if ($authentication['status'] === 'locked') {
            $this->recordAuthenticationEvent($request, 'auth.login_blocked', $user, [
                'locked_until' => $user->locked_until?->toIso8601String(),
                'manual_lock' => $user->is_manually_locked,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'This account is temporarily locked. Please try again later.',
                'errors' => ['employeeId' => ['Too many unsuccessful sign-in attempts.']],
            ], 423);
        }

        if (! $user) {
            Hash::check($credentials['password'], self::DUMMY_PASSWORD_HASH);
        }

        if ($authentication['status'] === 'invalid') {
            $this->recordAuthenticationEvent($request, 'auth.login_failed', $user, [
                'employee_id' => $credentials['employeeId'],
            ]);

            throw ValidationException::withMessages([
                'employeeId' => ['The Employee ID or password is incorrect.'],
            ]);
        }

        Auth::login($user, (bool) ($credentials['remember'] ?? false));
        $request->session()->regenerate();

        $this->recordAuthenticationEvent($request, 'auth.login_succeeded', $user);

        return response()->json([
            'success' => true,
            'message' => 'Signed in successfully.',
            'data' => [
                'user' => new UserResource(
                    $user->fresh(['office', 'role.permissions', 'roles.permissions']),
                ),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['user' => new UserResource($request->user())],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->recordAuthenticationEvent($request, 'auth.logout', $user);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Signed out successfully.',
        ]);
    }

    /** @param array<string, mixed> $metadata */
    private function recordAuthenticationEvent(
        Request $request,
        string $action,
        ?User $user,
        array $metadata = [],
    ): void {
        $description = match ($action) {
            'auth.login_succeeded' => "{$user?->name} signed in.",
            'auth.logout' => "{$user?->name} signed out.",
            'auth.login_blocked' => "A blocked sign-in was attempted for {$user?->employee_id}.",
            default => 'A failed sign-in attempt was recorded for '.($user?->employee_id ?? ($metadata['employee_id'] ?? 'an unknown Employee ID')).'.',
        };

        ActivityRecorder::record(
            $request,
            $action,
            $description,
            $user,
            metadata: $metadata ?: null,
        );
    }
}
