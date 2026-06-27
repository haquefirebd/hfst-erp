<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Missing or invalid token format.'
            ], 401);
        }

        $token = substr($authHeader, 7);

        if ($token !== 'hfst_erp_admin_secret_token_2026') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Invalid authentication credentials.'
            ], 401);
        }

        return $next($request);
    }
}
