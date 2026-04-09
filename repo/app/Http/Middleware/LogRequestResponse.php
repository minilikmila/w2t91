<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequestResponse
{
    /**
     * Sensitive fields that should not be logged.
     */
    private const REDACTED_FIELDS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'authorization',
        'email',
        'phone',
        'guardian_contact',
        'guardian_name',
        'address',
        'date_of_birth',
        'first_name',
        'last_name',
        'answers',
        'ssn',
        'national_id',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->logRequest($request, $response, $duration);

        return $response;
    }

    private function logRequest(Request $request, Response $response, float $durationMs): void
    {
        $statusCode = $response->getStatusCode();
        $method = $request->method();
        $path = $request->path();
        $userId = $request->user()?->id;

        $context = [
            'method' => $method,
            'path' => $path,
            'status' => $statusCode,
            'duration_ms' => $durationMs,
            'ip' => $request->ip(),
            'user_id' => $userId,
            'user_agent' => substr($request->userAgent() ?? '', 0, 200),
        ];

        // Include sanitized request params for non-GET requests
        if (!$request->isMethod('GET')) {
            $context['params'] = $this->sanitizeParams($request->all());
        }

        // Log level based on status code
        $message = "{$method} /{$path} → {$statusCode} ({$durationMs}ms)";

        if ($statusCode >= 500) {
            Log::error($message, $context);
        } elseif ($statusCode >= 400) {
            Log::warning($message, $context);
        } else {
            Log::info($message, $context);
        }
    }

    private function sanitizeParams(array $params): array
    {
        $sanitized = [];

        foreach ($params as $key => $value) {
            if (in_array(strtolower($key), self::REDACTED_FIELDS)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeParams($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
