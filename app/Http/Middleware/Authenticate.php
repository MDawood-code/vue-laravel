<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Override;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    #[Override]
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('login');
    }

    /** @param  array<mixed>  $guards */
    #[Override]
    protected function unauthenticated($request, $guards)
    {
        abort(response()->json([
            'success' => 'false',
            'data' => [],
            'message' => 'Unauthenticated Request',
        ], 401));
    }
}
