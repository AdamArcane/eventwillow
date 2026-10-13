<?php

namespace App\Modules\EventWillowBranding;

use App\Services\TranslationOverrideService;
use Illuminate\Support\ServiceProvider as BaseProvider;
use Illuminate\Translation\FileLoader;

class ServiceProvider extends BaseProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config.php', 'eventwillow-branding');

        // The loader resolves before the translator. AppServiceProvider adds the
        // operator's path when the translator resolves, so operator edits stay last.
        $this->callAfterResolving('translation.loader', function (FileLoader $loader) {
            $loader->addPath(__DIR__.'/lang');
        });

        $this->app->bind(TranslationOverrideService::class, BrandingTranslationOverrideService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/views', 'eventwillow-branding');
    }
}
