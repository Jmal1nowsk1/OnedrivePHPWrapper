<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('services.onedrive.token');

        if (empty($token) || $request->header('Authorization') !== $token) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        return $next($request);
    }
}

