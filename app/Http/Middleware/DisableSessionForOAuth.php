<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DisableSessionForOAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('_disable_session', true);

        return $next($request);
    }
}
