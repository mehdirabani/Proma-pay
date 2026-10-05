<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $candidate = $request->header('X-Request-Id');
        $requestId = is_string($candidate) && Str::isUuid($candidate) ? $candidate : (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);

        return $next($request)->header('X-Request-Id', $requestId);
    }
}
