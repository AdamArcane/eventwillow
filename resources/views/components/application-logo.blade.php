@php
    $logoUrls = [];
    foreach (['dark', 'light'] as $variant) {
        $path = ltrim(config('app.logo_' . $variant), '/');
        $url = asset($path);
        $file = public_path($path);
        if (is_file($file)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . substr(hash_file('sha256', $file), 0, 12);
        }
        $logoUrls[$variant] = $url;
    }
@endphp
<div class="p-6">
    <img class="h-10 md:h-12 w-auto dark:hidden" src="{{ $logoUrls['dark'] }}" alt="{{ config('app.name') }}"/>
    <img class="h-10 md:h-12 w-auto hidden dark:block" src="{{ $logoUrls['light'] }}" alt="{{ config('app.name') }}"/>
</div>
