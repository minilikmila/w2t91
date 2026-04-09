<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLockout
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isLockedOut()) {
            return response()->json([
                'error' => 'Locked',
                'message' => 'Account is temporarily locked due to too many failed login attempts. Try again later.',
                'locked_until' => $user->locked_until->toIso8601String(),
            ], 423);
        }

        return $next($request);
    }
}
