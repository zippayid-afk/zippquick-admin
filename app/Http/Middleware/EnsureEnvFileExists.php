<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DISABLED: All installation checks removed for live server.
 * Middleware bypassed - all requests allowed through.
 */
class EnsureEnvFileExists
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
