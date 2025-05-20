<?php

namespace App\Http\Middleware;

use Illuminate\Http\RedirectResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request):((\Illuminate\Http\Response|RedirectResponse)) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(auth()->check() && auth()->user()->type !== USER_TYPE_SUPER_ADMIN, response()->json([
            'success' => 'false',
            'data' => [],
            'message' => 'Unauthorized Request',
        ], 401));

        return $next($request);
    }
}
