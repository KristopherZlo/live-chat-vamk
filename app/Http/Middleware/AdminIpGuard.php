<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminIpGuard
{
    /**
     * Allow admin access only for whitelisted IPs if configured.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = config('app.admin_allowed_ips', '');
        if ($allowed) {
            $allowedList = collect(explode(',', $allowed))
                ->map(fn ($ip) => trim($ip))
                ->filter()
                ->all();

            if (!empty($allowedList) && !in_array($request->ip(), $allowedList, true)) {
                abort(403, 'Admin access denied from this IP');
            }
        }

        return $next($request);
    }
}
