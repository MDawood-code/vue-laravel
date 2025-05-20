<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackCompanyLastActiveAt
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request):Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $company = $request->user()->company;

        if ($request->user()->company()->exists()) {
            $lastActiveAt = $company->last_active_at ? Carbon::parse($company->last_active_at) : null;

            if (!$lastActiveAt || !$lastActiveAt->isToday()) {
                $company->last_active_at = now();
                $company->save();
            }
        }

        return $next($request);
    }
}
