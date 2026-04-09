<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'Authentication is required.',
            ], 401);
        }

        if (!$user->hasAnyPermission($permissions)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'You do not have the required permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}
