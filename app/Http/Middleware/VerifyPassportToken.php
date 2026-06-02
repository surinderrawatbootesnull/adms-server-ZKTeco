<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;

class VerifyPassportToken
{
    public function handle(Request $request, Closure $next)
    {
        try {

            // first check bearer token
            $token = $request->bearerToken();

            // if not found, check session
            if (!$token) {
                $token = session('auth_token');
            }

            if (!$token) {
                throw new \Exception('No token found');
            }

            $publicKey = file_get_contents(
                storage_path('oauth-public.key')
            );

            $decoded = JWT::decode(
                $token,
                new Key($publicKey, 'RS256')
            );

            $claims = (array) $decoded;

            $request->attributes->set(
                'auth_user_claims',
                $claims
            );

        } catch(\Exception $e) {

            return redirect('/login');
        }

        return $next($request);
    }
}