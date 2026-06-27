<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    /**
     * Authenticate admin credentials and return access token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $request->input('email');
        $password = $request->input('password');

        if ($email === 'admin@hfsterp.com' && $password === 'admin123') {
            return response()->json([
                'success' => true,
                'token' => 'hfst_erp_admin_secret_token_2026',
                'user' => [
                    'name' => 'HFST System Administrator',
                    'email' => 'admin@hfsterp.com',
                    'role' => 'Admin',
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid email or password credentials.',
        ], 401);
    }
}
