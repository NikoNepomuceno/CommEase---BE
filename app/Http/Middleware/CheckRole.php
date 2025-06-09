<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        Log::info('Role check:', [
            'session_id' => session()->getId(),
            'user' => $request->user() ? [
                'id' => $request->user()->id,
                'email' => $request->user()->email,
                'role' => $request->user()->role
            ] : null,
            'required_roles' => explode(',', $roles),
            'request_path' => $request->path(),
            'request_method' => $request->method()
        ]);

        if (!$request->user()) {
            Log::warning('Unauthorized access attempt - No user found');
            return response()->json(['message' => 'Unauthorized. Please login.'], 401);
        }

        $allowedRoles = explode(',', $roles);

        if (!in_array($request->user()->role, $allowedRoles)) {
            Log::warning('Unauthorized access attempt - Invalid role', [
                'user_role' => $request->user()->role,
                'required_roles' => $allowedRoles
            ]);
            return response()->json(['message' => 'Unauthorized. Insufficient permissions.'], 403);
        }

        Log::info('Role check passed', [
            'user_role' => $request->user()->role,
            'required_roles' => $allowedRoles
        ]);

        return $next($request);
    }
}
