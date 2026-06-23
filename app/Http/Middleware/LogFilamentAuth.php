<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogFilamentAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::info('[LogFilamentAuth] Request received', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'auth_check' => \Auth::check(),
            'auth_user_id' => \Auth::id(),
            'auth_user_email' => \Auth::user()?->email,
            'session_id' => session()->getId(),
            'has_session' => $request->hasSession(),
            'session_started' => $request->session()->isStarted(),
            'cookies' => array_keys($request->cookies->all()),
        ]);

        $response = $next($request);

        Log::info('[LogFilamentAuth] Response created', [
            'status_code' => $response->getStatusCode(),
            'auth_check_after' => \Auth::check(),
        ]);

        return $response;
    }
}
