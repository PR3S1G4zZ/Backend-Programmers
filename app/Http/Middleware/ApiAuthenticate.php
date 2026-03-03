<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class ApiAuthenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // For API requests, don't redirect - just return null
        // The handle method will return 401 Unauthorized
        return null;
    }

    /**
     * Handle an unauthenticated user.
     */
    protected function unauthenticated($request, array $guards)
    {
        \Log::warning('API Authentication failed:', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'bearer_token_exists' => !empty($request->bearerToken()),
            'guards' => $guards,
        ]);
        
        abort(response()->json(['message' => 'No autenticado'], 401));
    }
}
