<?php

namespace Tests\Feature;

use App\Services\TranslationOverrideService;
use Illuminate\Support\Facades\File;
use Tests\Feature\Concerns\ResetsTranslationOverrides;
use Tests\TestCase;

class EventWillowBrandingTranslationsTest extends TestCase
{
    use ResetsTranslationOverrides;

    public function test_branding_overrides_the_upstream_text_without_changing_its_catalog(): void
    {
        $upstream = require resource_path('lang/en/messages.php');

        $this->assertSame('Schedule Name', $upstream['schedule_name']);
        $this->assertSame('Channel Name', __('messages.schedule_name'));
        $this->assertSame($upstream['log_in'], __('messages.log_in'));
    }

    public function test_organizer_replaces_the_schedule_type_label_not_the_performer_label(): void
    {
        $upstream = require resource_path('lang/en/messages.php');

        $this->assertSame('Talent', $upstream['talent']);
        $this->assertSame('Organizer', __('messages.talent'));
        $this->assertSame('Organizer Channels', __('messages.talent_schedules'));
        $this->assertSame('Talents', __('messages.talents'));
    }

    public function test_the_translations_editor_uses_the_branded_default(): void
    {
        $defaults = app(TranslationOverrideService::class)->readShipped('en', 'messages');

        $this->assertSame('Channel Name', $defaults['schedule_name']);
        $this->assertSame(__('messages.schedule_name'), $defaults['schedule_name']);
        $this->assertArrayHasKey('concerts_&_performances', $defaults);
    }

    public function test_operator_edits_win_and_removing_them_restores_branding(): void
    {
        $path = config('app.lang_overrides_path').'/en/messages.php';
        File::ensureDirectoryExists(dirname($path));
        File::put($path, "<?php return ['schedule_name' => 'Organizer Calendar Name'];\n");
        app('translator')->setLoaded([]);

        $this->assertSame('Organizer Calendar Name', __('messages.schedule_name'));
        $this->assertSame('Channel Name', app(TranslationOverrideService::class)->readShipped('en', 'messages')['schedule_name']);

        File::delete($path);
        app('translator')->setLoaded([]);

        $this->assertSame('Channel Name', __('messages.schedule_name'));
    }

    public function test_all_locales_use_the_branded_defaults_and_additional_categories(): void
    {
        foreach (array_keys(config('app.supported_languages')) as $locale) {
            $defaults = app(TranslationOverrideService::class)->readShipped($locale, 'messages');
            $this->assertSame($defaults['schedule_name'], trans('messages.schedule_name', [], $locale), $locale);
            $this->assertArrayHasKey('concerts_&_performances', $defaults, $locale);
            $this->assertSame($defaults['concerts_&_performances'], trans('messages.concerts_&_performances', [], $locale), $locale);
        }
    }

    public function test_branding_keeps_translation_placeholders_and_time_scheduling_words(): void
    {
        $this->assertSame('The last run stopped on its time budget rather than running out of rows, so the queue is draining as fast as the schedule allows.', __('messages.translation_budget_reached'));
        $this->assertStringContainsString(':count', app(TranslationOverrideService::class)->readShipped('en', 'messages')['owner_digest_subject_many']);
    }

    public function test_rebuilding_the_application_keeps_translation_precedence(): void
    {
        $this->refreshApplication();

        $this->assertSame('Channel Name', __('messages.schedule_name'));
        $this->assertSame('Channel Name', app(TranslationOverrideService::class)->readShipped('en', 'messages')['schedule_name']);
    }
}
