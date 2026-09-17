<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Allow a request only when the authenticated user has the requested role.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user || ! $user->hasRole($role)) {
            return response()->json([
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}
