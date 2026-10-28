<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsManager
{
    /** Admins and managers only (products, stock, reports, users). */
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isManager(), 403);

        return $next($request);
    }
}
