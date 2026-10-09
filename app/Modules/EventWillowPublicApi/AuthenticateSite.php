<?php
namespace App\Modules\EventWillowPublicApi;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

final class AuthenticateSite
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('eventwillow-public.site_key', '');
        $provided = (string) $request->header('X-EventWillow-Site-Key', '');
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            return response()->json(['error' => 'Unauthorized'], 401)->header('Cache-Control', 'no-store');
        }
        // Only an explicitly disabled allowlist in the local environment can bypass IP checks.
        // The global key above remains mandatory in every environment.
        if (app()->environment('local') && config('eventwillow-public.enforce_ip_allowlist', true) === false) {
            return $next($request);
        }
        $allowed = config('eventwillow-public.allowed_ips', []);
        $source = (string) $request->server('REMOTE_ADDR');
        $proxies = config('eventwillow-public.trusted_proxy_ips', []);
        if ($proxies && IpUtils::checkIp($source, $proxies)) {
            $chain = array_map('trim', explode(',', (string) $request->header('X-Forwarded-For', '')));
            $chain[] = $source;
            while ($chain && IpUtils::checkIp(end($chain), $proxies)) array_pop($chain);
            $source = (string) end($chain);
        }
        // Own explicit proxy trust boundary; never inherit an application's wildcard proxy trust.
        if (!$allowed || !filter_var($source, FILTER_VALIDATE_IP) || !IpUtils::checkIp($source, $allowed)) {
            return response()->json(['error' => 'Forbidden'], 403)->header('Cache-Control', 'no-store');
        }
        return $next($request);
    }
}
