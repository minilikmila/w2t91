<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Password must be at least 12 characters with uppercase, lowercase, digit, and special character.
     */
    private const PASSWORD_PATTERN = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{12,}$/';

    private const TOKEN_LIFETIME_HOURS = 24;

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if ($user->isLockedOut()) {
            return response()->json([
                'error' => 'Locked',
                'message' => 'Account is temporarily locked due to too many failed login attempts.',
                'locked_until' => $user->locked_until->toIso8601String(),
            ], 423);
        }

        if (!$user->is_active) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Account is inactive.',
            ], 401);
        }

        if (!Hash::check($request->password, $user->password)) {
            $user->incrementFailedAttempts();

            $response = [
                'error' => 'Unauthorized',
                'message' => 'Invalid credentials.',
            ];

            if ($user->isLockedOut()) {
                $response['message'] = 'Account locked due to too many failed attempts.';
                $response['locked_until'] = $user->locked_until->toIso8601String();
            }

            return response()->json($response, 401);
        }

        // Successful login
        $user->resetFailedAttempts();
        $user->update(['last_login_at' => now()]);

        // Generate token
        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        $apiToken = ApiToken::create([
            'user_id' => $user->id,
            'token' => $hashedToken,
            'expires_at' => now()->addHours(self::TOKEN_LIFETIME_HOURS),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $user->load('role.permissions');

        return response()->json([
            'message' => 'Login successful.',
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_at' => $apiToken->expires_at->toIso8601String(),
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $apiToken = $request->attributes->get('api_token');

        if ($apiToken) {
            $apiToken->revoke();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('role.permissions');

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string|unique:users,username|min:3|max:50',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => [
                'required',
                'string',
                'min:12',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (!preg_match(self::PASSWORD_PATTERN, $value)) {
                        $fail('Password must contain at least one uppercase letter, one lowercase letter, one digit, and one special character.');
                    }
                },
            ],
            'role_id' => 'nullable|exists:roles,id',
        ]);

        $user = User::create([
            'username' => $request->username,
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role_id' => $request->role_id,
        ]);

        $user->load('role.permissions');

        return response()->json([
            'message' => 'User registered successfully.',
            'user' => new UserResource($user),
        ], 201);
    }
}
