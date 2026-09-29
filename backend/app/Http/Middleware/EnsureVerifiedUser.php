<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerifiedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! in_array($user->role, ['admin', 'super_admin'], true) && ! $user->hasVerifiedEmail()) {
            return response()->json([
                'code' => 'email_verification_required',
                'message' => 'Verify your email address before accessing the user platform.',
            ], 403);
        }

        return $next($request);
    }
}
