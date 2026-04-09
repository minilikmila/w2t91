<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'A valid API token is required.',
            ], 401);
        }

        $hashedToken = hash('sha256', $bearerToken);
        $apiToken = ApiToken::where('token', $hashedToken)
            ->where('is_revoked', false)
            ->first();

        if (!$apiToken || !$apiToken->isValid()) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Invalid or expired token.',
            ], 401);
        }

        $user = $apiToken->user;

        if (!$user || !$user->is_active) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'User account is inactive.',
            ], 401);
        }

        $apiToken->update(['last_used_at' => now()]);

        $request->merge(['api_token' => $apiToken]);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
