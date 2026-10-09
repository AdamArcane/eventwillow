<?php
namespace App\Modules\EventWillowPublicApi;

use Illuminate\Support\ServiceProvider as BaseProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

class ServiceProvider extends BaseProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config.php', 'eventwillow-public');
    }

    public function boot(): void
    {
        RateLimiter::for('eventwillow-public', fn ($request) => Limit::perMinute((int) config('eventwillow-public.requests_per_minute', 120))->by($request->ip()));
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
