<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckReseller
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request):Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->type === USER_TYPE_RESELLER) {
            return $next($request);
        }
        abort(response()->json([
            'success' => 'false',
            'data' => [],
            'message' => 'Unauthorized Request',
        ], 401));
    }
}
