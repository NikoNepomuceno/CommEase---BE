<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        Log::info('Authentication check:', [
            'user' => Auth::user() ? [
                'id' => Auth::user()->id,
                'email' => Auth::user()->email,
                'role' => Auth::user()->role
            ] : null,
            'auth_type' => 'token',
            'has_bearer_token' => $request->bearerToken() ? true : false
        ]);

        return $request->expectsJson() ? null : route('login');
    }
}
