<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DisableSessionForOAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        if (str_starts_with($path, 'auth/google') || str_starts_with($path, 'auth/github')) {
            config(['session.driver' => 'array']);
        }

        return $next($request);
    }
}
