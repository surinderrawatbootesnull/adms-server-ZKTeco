<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('login');
    }

    public function login(Request $request)
    {

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        try {
            $response = Http::post(
                rtrim(env('AUTH_API_URL'), '/') . '/oauth/login',
                [
                    'email' => $request->email,
                    'password' => $request->password,
                ]
            );

            if (!$response->successful()) {
                return back()
                    ->withInput($request->only('email'))
                    ->with('error', 'Invalid credentials.');
            }

            $data = $response->json();

            $token = $data['token'] ?? null;

            if (!$token) {
                return back()
                    ->withInput($request->only('email'))
                    ->with('error', 'Authentication token missing.');
            }

            session([
                'auth_token' => $token,
                'auth_user' => $data['user'] ?? [],
            ]);

            return redirect('/devices');

        } catch (\Exception $e) {

            logger()->error('Authentication Error', [
                'message' => $e->getMessage(),
            ]);

            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Unable to connect to authentication server.');
        }
    }

    public function logout()
    {
        session()->forget([
            'auth_token',
            'auth_user',
        ]);

        return redirect('/login');
    }
}