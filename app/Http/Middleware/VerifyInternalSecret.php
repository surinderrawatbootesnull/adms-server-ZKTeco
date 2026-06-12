<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $passedSecret = $request->header('x-internal-secret');
        $configuredSecret = env('INTERNAL_WEBHOOK_SECRET');

        if (blank($configuredSecret) || $passedSecret !== $configuredSecret) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid or missing internal webhook secret.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}