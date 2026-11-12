<?php

declare(strict_types=1);

namespace KaziOmar\ApiResponder\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    /**
     * Handle an incoming request.
     *
     * Ensures the request header contains 'Accept: application/json' so that
     * authentication, validation, and route resolution always treat it as an API call.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
