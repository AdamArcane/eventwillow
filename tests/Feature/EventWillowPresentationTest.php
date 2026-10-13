<?php

namespace Tests\Feature;

use App\Modules\EventWillowBranding\Assets;
use Tests\TestCase;

class EventWillowPresentationTest extends TestCase
{
    public function test_auth_and_navigation_logos_share_content_fingerprints(): void
    {
        config(['app.logo_dark' => '/images/dark_logo.png', 'app.logo_light' => '/images/light_logo.png']);
        $auth = view('components.application-logo')->render();
        $navigation = view('eventwillow-branding::navigation-logo')->render();

        foreach (['dark', 'light'] as $variant) {
            $url = asset('images/'.$variant.'_logo.png').'?v='.substr(hash_file('sha256', public_path('images/'.$variant.'_logo.png')), 0, 12);
            $this->assertSame($url, Assets::logoUrl($variant));
            $this->assertStringContainsString(e($url), $auth);
        }

        $this->assertStringContainsString(e(Assets::logoUrl('light')), $navigation);
        $this->assertStringContainsString('alt="EventWillow"', $navigation);
        $this->assertStringContainsString('dark:hidden', $auth);
        $this->assertStringContainsString('hidden dark:block', $auth);
    }

    public function test_configured_external_logos_are_used_without_local_file_fingerprints(): void
    {
        config(['app.logo_light' => 'https://example.com/custom-logo.png']);

        $this->assertSame('https://example.com/custom-logo.png', Assets::logoUrl('light'));
    }

    public function test_the_prepaint_theme_uses_branding_defaults_with_saved_preferences_first(): void
    {
        $html = view('partials.theme-script', ['variants' => true])->render();

        $this->assertStringContainsString('DEFAULT_LIGHT = "sand", DEFAULT_DARK = "carbon"', $html);
        $this->assertStringContainsString('var DEFAULT_MODE = "light"', $html);
        $this->assertStringContainsString('return get(KEY) || DEFAULT_MODE', $html);
        $this->assertStringContainsString('allowed.indexOf(stored) !== -1', $html);
    }

    public function test_theme_controls_and_prepaint_script_share_configurable_defaults(): void
    {
        config([
            'eventwillow-branding.theme.mode' => 'system',
            'eventwillow-branding.theme.light_variant' => 'paper',
            'eventwillow-branding.theme.dark_variant' => 'midnight',
        ]);
        $script = view('partials.theme-script', ['variants' => true])->render();
        $picker = view('components.theme-picker')->render();

        $this->assertStringContainsString('DEFAULT_LIGHT = "paper", DEFAULT_DARK = "midnight"', $script);
        $this->assertStringContainsString('var DEFAULT_MODE = "system"', $script);
        $this->assertStringContainsString('var mode = "system"', $picker);
        $this->assertStringContainsString("localStorage.getItem('theme') || \"system\"", $picker);
    }

    public function test_documentation_source_links_use_the_shared_repository_setting(): void
    {
        config(['eventwillow-branding.repository_url' => 'https://example.com/our-repository/']);
        $page = new \App\View\Components\DocsPage('gift-cards');

        $this->assertSame('https://example.com/our-repository/blob/main/resources/views/marketing/docs/gift-cards.blade.php', $page->sourceUrl());
    }

    public function test_contact_links_keep_the_existing_accounts_and_repository(): void
    {
        $socials = config('eventwillow-branding.contact_socials');
        $this->assertSame(['Facebook', 'Instagram', 'YouTube', 'GitHub'], array_column($socials, 'name'));
        $this->assertSame('https://www.facebook.com/arcanetechsolutions', $socials[0]['href']);
        $this->assertSame('https://www.instagram.com/arcanetechsolutions/', $socials[1]['href']);
        $this->assertSame('https://youtube.com/@arcanetechsolutions', $socials[2]['href']);
        $this->assertSame(config('eventwillow-branding.repository_url'), $socials[3]['href']);
    }
}
