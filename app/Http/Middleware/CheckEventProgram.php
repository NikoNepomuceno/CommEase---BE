<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;

class CheckEventProgram
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $event = $request->route('event');

        // If event is not found, let the controller handle the 404
        if (!$event) {
            return $next($request);
        }

        // Get the authenticated user's program
        $userProgram = Auth::user()->program;

        // Check if the user's program is in the event's programs array
        if (!in_array($userProgram, $event->programs)) {
            return response()->json([
                'message' => 'You do not have access to this event. Only volunteers from the specified programs can view this event.'
            ], 403);
        }

        return $next($request);
    }
}