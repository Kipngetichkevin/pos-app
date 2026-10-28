<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;

/** Cloud side: a branch server proves who it is with the token issued by `php artisan pos:branch-token`. */
class AuthenticateBranch
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('pos.mode') === 'cloud', 404);

        $token = $request->bearerToken();
        $branch = $token ? Branch::where('sync_token_hash', hash('sha256', $token))->where('is_active', true)->first() : null;

        abort_unless($branch, 401, 'Unknown or disabled branch token.');

        $request->attributes->set('branch', $branch);

        return $next($request);
    }
}
