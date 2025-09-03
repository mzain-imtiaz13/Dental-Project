<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLoginForm()
    {
        return view('login'); // resources/views/login.blade.php
    }

    /**
     * Handle login request.
     */
    public function login(LoginRequest $request)
    {
        try {
            $request->authenticate();

            $request->session()->regenerate();

            // Redirect based on role
            if (Auth::user()->role === 'super_admin') {
                if ($request->expectsJson()) {
                    return response()->json(['redirect' => url('/dashboard')]);
                }
                return redirect()->intended('/dashboard');
            }

            if ($request->expectsJson()) {
                return response()->json(['redirect' => url('/home')]);
            }
            return redirect()->intended('/home');
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                $errors = $e->errors();
                $firstError = collect($errors)->flatten()->first() ?? 'Invalid credentials.';
                return response()->json([
                    'message' => $firstError,
                    'errors' => $errors,
                    'code' => 'AUTHENTICATION_FAILED',
                ], 422);
            }
            throw $e;
        }
    }

    /**
     * Logout user.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
