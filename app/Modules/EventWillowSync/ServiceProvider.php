<?php

namespace App\Modules\EventWillowSync;

use Illuminate\Support\ServiceProvider as BaseProvider;

class ServiceProvider extends BaseProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([Commands\UpstreamSync::class]);
        }
    }
}
