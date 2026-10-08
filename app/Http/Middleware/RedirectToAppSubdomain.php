<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToAppSubdomain
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.is_testing') || ! config('app.hosted')) {
            return $next($request);
        }

        if ($request->getHost() !== parse_url(app_url('/'), PHP_URL_HOST)) {
            return redirect(app_url($request->getRequestUri()), 302);
        }

        return $next($request);
    }
}
