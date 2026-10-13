<?php

namespace App\Modules\EventWillowBranding;

use App\Services\TranslationOverrideService;

class BrandingTranslationOverrideService extends TranslationOverrideService
{
    /**
     * The editor, its revert action, and suggestion validation use the same
     * EventWillow defaults as the translator, including EventWillow-only keys.
     */
    public function readShipped(string $locale, string $group): array
    {
        $upstream = parent::readShipped($locale, $group);
        $path = __DIR__."/lang/{$locale}/{$group}.php";

        return is_file($path) ? array_replace_recursive($upstream, require $path) : $upstream;
    }
}
