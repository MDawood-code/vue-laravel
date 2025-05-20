<?php

namespace App\Http\Middleware;

use Illuminate\Http\RedirectResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminOrAgent
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request):((\Illuminate\Http\Response|RedirectResponse)) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && (auth()->user()->type === USER_TYPE_ADMIN || auth()->user()->type === USER_TYPE_SUPER_ADMIN || (auth()->user()->type === USER_TYPE_ADMIN_STAFF && auth()->user()->is_support_agent == true))) {
            return $next($request);
        }
        abort(response()->json([
            'success' => 'false',
            'data' => [],
            'message' => 'Unauthorized Request',
        ], 401));
    }
}
