<?php

namespace App\Http\Middleware;

use Illuminate\Http\RedirectResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request):((\Illuminate\Http\Response|RedirectResponse)) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(auth()->check() && ! in_array(auth()->user()->type, [USER_TYPE_ADMIN, USER_TYPE_SUPER_ADMIN, USER_TYPE_ADMIN_STAFF]), response()->json([
            'success' => 'false',
            'data' => [],
            'message' => 'Unauthorized Request',
        ], 401));

        return $next($request);
    }
}
