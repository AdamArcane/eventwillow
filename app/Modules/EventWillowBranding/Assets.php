<?php

namespace App\Modules\EventWillowBranding;

class Assets
{
    public static function logoUrl(string $variant): string
    {
        $path = ltrim(config('app.logo_'.$variant), '/');
        $url = asset($path);
        $file = public_path($path);

        if (is_file($file)) {
            $url .= (str_contains($url, '?') ? '&' : '?').'v='.substr(hash_file('sha256', $file), 0, 12);
        }

        return $url;
    }
}
