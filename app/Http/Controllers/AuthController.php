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
      
        $response = Http::post(
              env('AUTH_API_URL'),
            [
                'email' => $request->email,
                'password' => $request->password,
            ]
        );
        if ($response->successful()) {
        
            $data = $response->json();

            // token exists here
            $token = $data['data']['token'] ?? null;

            if (!$token) {
                return back()->with('error', 'Token missing');
            }

            // store token in session
            session([
                'auth_token' => $token
            ]);

            return redirect('/devices');
        }

        return back()->with('error', 'Invalid credentials');
    
}
    public function logout()
    {
        session()->forget('auth_token');

        return redirect('/login');
    }
}