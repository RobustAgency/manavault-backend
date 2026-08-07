<?php

namespace App\Http\Middleware;

use Closure;
use Throwable;
use App\Models\LoginLog;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogLoginActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Record the login attempt once the response has been sent to the client.
     */
    public function terminate(Request $request, Response $response): void
    {
        $email = $request->input('email');

        if (! is_string($email) || trim($email) === '') {
            return;
        }

        try {
            LoginLog::create([
                'email' => Str::limit(trim($email), 255, ''),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                'activity' => $response->getStatusCode() < 400 ? 'login' : 'failed_login',
                'logged_in_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Logging must never break authentication for the user.
            Log::error('Failed to record login log', [
                'email' => $email,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
