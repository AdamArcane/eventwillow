<?php

namespace Tests\Feature;

use App\Services\TranslationOverrideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ResetsTranslationOverrides;
use Tests\TestCase;

class EventWillowBrandingOverridesTest extends TestCase
{
    use RefreshDatabase;
    use ResetsTranslationOverrides;

    public function test_saving_then_reverting_an_operator_edit_restores_the_branded_default(): void
    {
        $service = app(TranslationOverrideService::class);
        $branding = app_path('Modules/EventWillowBranding/lang/en/messages.php');
        $hash = hash_file('sha256', $branding);

        $saved = $service->saveOverrides('en', 'messages', ['schedule_name' => 'Organizer Calendar Name'], null);
        $this->assertSame(1, $saved['saved']);
        $this->assertSame('Organizer Calendar Name', __('messages.schedule_name'));

        $removed = $service->revert('en', 'messages', ['schedule_name']);
        $this->assertSame(1, $removed);
        $this->assertSame('Channel Name', __('messages.schedule_name'));
        $this->assertSame($hash, hash_file('sha256', $branding));
    }

    public function test_operator_edits_accept_eventwillow_only_keys_and_can_restore_their_defaults(): void
    {
        $service = app(TranslationOverrideService::class);
        $key = 'concerts_&_performances';
        $saved = $service->saveOverrides('en', 'messages', [$key => 'Live Performances'], null);

        $this->assertSame(1, $saved['saved']);
        $this->assertSame('Live Performances', trans('messages.'.$key));

        $removed = $service->saveOverrides('en', 'messages', [$key => 'Concerts & Performances'], null);
        $this->assertSame(1, $removed['removed']);
        $this->assertSame('Concerts & Performances', trans('messages.'.$key));
    }
}
