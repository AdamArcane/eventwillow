<?php
namespace App\Modules\EventWillowPublicApi\Tests;
use App\Models\Event;
use App\Models\Role;
use App\Modules\EventWillowPublicApi\Serializer;
use App\Utils\UrlUtils;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesScheduleData;
use Tests\TestCase;

class SiteApiTest extends TestCase {
    use RefreshDatabase, CreatesScheduleData;
    private string $prefix = '/api/eventwillow/v1/';
    protected function setUp(): void {
        parent::setUp();
        config(['eventwillow-public.enforce_ip_allowlist'=>true, 'eventwillow-public.site_key'=>'fixture-site-key','eventwillow-public.allowed_ips'=>['127.0.0.1'], 'eventwillow-public.trusted_proxy_ips'=>[], 'app.search_exclude_country'=>'']);
        $this->withHeaders(['X-EventWillow-Site-Key'=>'fixture-site-key','Accept'=>'application/json']);
    }
    private function fixture(array $attrs=[]): array {
        $role=$this->createRole($this->createOwner(),'venue',['name'=>'Willow Studio','subdomain'=>'willow-studio','city'=>'Brooklyn','state'=>'Connecticut','country_code'=>'US','hide_past_events'=>false]);
        $event=$this->createEvent($role, array_merge(['name'=>'Autumn Gathering','creator_role_id'=>$role->id],$attrs));
        return [$role,$event,UrlUtils::encodeId($event->id)];
    }
    public function test_key_and_source_are_required_on_every_endpoint(): void {
        [$role,$event,$id]=$this->fixture();
        foreach (['events','events/'.$id,'channels','channels/'.$role->subdomain,'channels/'.$role->subdomain.'/events','facets'] as $path) {
            $this->withHeaders(['X-EventWillow-Site-Key'=>''])->getJson($this->prefix.$path)->assertUnauthorized();
            $this->withHeaders(['X-EventWillow-Site-Key'=>'fixture-site-key'])->getJson($this->prefix.$path)->assertOk();
        }
        config(['eventwillow-public.allowed_ips'=>[]]);
        $this->getJson($this->prefix.'events')->assertForbidden();
    }
    public function test_local_ip_bypass_still_requires_the_global_key_on_every_endpoint(): void {
        [$role,$event,$id]=$this->fixture();
        $original=$this->app->environment();
        $this->app->instance('env','local');
        config(['eventwillow-public.enforce_ip_allowlist'=>false, 'eventwillow-public.allowed_ips'=>[]]);
        try {
            foreach (['events','events/'.$id,'channels','channels/'.$role->subdomain,'channels/'.$role->subdomain.'/events','facets'] as $path) {
                $this->withHeaders(['X-EventWillow-Site-Key'=>'fixture-site-key'])->getJson($this->prefix.$path)->assertOk();
                foreach (['','regular-user-key'] as $key) {
                    $this->withHeaders(['X-EventWillow-Site-Key'=>$key])->getJson($this->prefix.$path)->assertUnauthorized();
                }
            }
            config(['eventwillow-public.site_key'=>'']);
            $this->withHeaders(['X-EventWillow-Site-Key'=>'fixture-site-key'])->getJson($this->prefix.'events')->assertUnauthorized();
        } finally { $this->app->instance('env',$original); }
    }
    public function test_ip_bypass_is_never_available_outside_local_and_requires_explicit_false(): void {
        $original=$this->app->environment();
        config(['eventwillow-public.allowed_ips'=>[], 'eventwillow-public.enforce_ip_allowlist'=>false]);
        try {
            foreach (['production','staging','testing'] as $environment) {
                $this->app->instance('env',$environment);
                $this->getJson($this->prefix.'events')->assertForbidden();
            }
            $this->app->instance('env','local');
            foreach ([true,null,'false',0] as $setting) {
                config(['eventwillow-public.enforce_ip_allowlist'=>$setting]);
                $this->getJson($this->prefix.'events')->assertForbidden();
            }
        } finally { $this->app->instance('env',$original); }
    }
    public function test_forwarded_headers_cannot_bypass_source_restrictions(): void {
        config(['eventwillow-public.allowed_ips'=>['203.0.113.1']]);
        $this->withHeaders(['X-Forwarded-For'=>'203.0.113.1'])->getJson($this->prefix.'events')->assertForbidden();
    }
    public function test_discovery_country_exclusion_applies_to_direct_channels_and_events(): void {
        [$role,$event,$id]=$this->fixture();
        config(['app.search_exclude_country'=>'us']);
        $this->getJson($this->prefix.'channels')->assertOk()->assertJsonCount(0,'data');
        $this->getJson($this->prefix.'channels/'.$role->subdomain)->assertNotFound();
        $this->getJson($this->prefix.'events/'.$id)->assertNotFound();
    }
    public function test_public_read_is_not_pro_gated_and_serializes_no_secrets(): void {
        [$role,$event,$id]=$this->fixture(['event_url'=>'https://private-join.invalid/secret','registration_url'=>'https://register.invalid']);
        $role->plan_type='free';$role->plan_expires=now()->subYear();$role->save();
        $r=$this->getJson($this->prefix.'events/'.$id)->assertOk()->assertJsonPath('data.id',$id);
        $this->assertStringNotContainsString('private-join.invalid',$r->getContent());
        $this->assertStringNotContainsString($role->email,$r->getContent());
        $r->assertHeader('Cache-Control','no-store, private');
    }
    public function test_image_fallback_uses_only_accepted_public_channel_profiles(): void {
        [$role,$event,$id]=$this->fixture(['flyer_image_url'=>null]);
        $role->profile_image_url='fixture/channel.jpg';$role->save();
        $this->getJson($this->prefix.'events/'.$id)->assertOk()->assertJsonPath('data.image',$role->getProfileImageUrl(960));
        $event->flyer_image_url='fixture/flyer.jpg';$event->save();
        $this->getJson($this->prefix.'events/'.$id)->assertOk()->assertJsonPath('data.image',$event->getImageUrl(960));
        $event->flyer_image_url=null;$event->save();$role->profile_image_url=null;$role->save();
        $hidden=$this->createRole($this->createOwner(),'talent',['name'=>'Hidden Performer','is_unlisted'=>true,'profile_image_url'=>'fixture/hidden.jpg']);
        $unaccepted=$this->createRole($this->createOwner(),'talent',['name'=>'Pending Performer','profile_image_url'=>'fixture/pending.jpg']);
        $event->roles()->attach($hidden->id,['is_accepted'=>true]);
        $event->roles()->attach($unaccepted->id,['is_accepted'=>false]);
        $this->getJson($this->prefix.'events/'.$id)->assertOk()->assertJsonPath('data.image',null);
        $curator=$this->createRole($this->createOwner(),'curator',['name'=>'Public Circle','profile_image_url'=>'fixture/circle.jpg']);
        $event->roles()->attach($curator->id,['is_accepted'=>true]);
        $event->creator_role_id=$curator->id;$event->save();
        $this->getJson($this->prefix.'events/'.$id)->assertOk()->assertJsonPath('data.image',$curator->getProfileImageUrl(960));
    }
    public function test_visibility_is_enforced_on_lists_and_direct_reads(): void {
        [$role,$event,$id]=$this->fixture();
        foreach (['is_draft','is_private','is_internal','is_cancelled','is_hidden_from_discovery'] as $flag) {
            $event->forceFill([$flag=>true])->save();
            $this->getJson($this->prefix.'events')->assertOk()->assertJsonCount(0,'data');
            $this->getJson($this->prefix.'events/'.$id)->assertNotFound();
            $event->forceFill([$flag=>false])->save();
        }
        $event->event_password='secret';$event->save();
        $this->getJson($this->prefix.'events/'.$id)->assertNotFound();
    }
    public function test_only_accepted_public_associations_are_returned(): void {
        [$role,$event,$id]=$this->fixture();
        $hidden=$this->createRole($this->createOwner(),'curator',['name'=>'Hidden Circle','is_unlisted'=>true]);
        $event->roles()->attach($hidden->id,['is_accepted'=>true]);
        $this->getJson($this->prefix.'events/'.$id)->assertOk()->assertJsonCount(1,'data.channels');
        $event->roles()->updateExistingPivot($role->id,['is_accepted'=>false]);
        $this->getJson($this->prefix.'events/'.$id)->assertNotFound();
    }
    public function test_withdrawn_channel_is_not_readable_and_demo_is_excluded(): void {
        [$role,$event,$id]=$this->fixture();
        $role->is_unlisted=true;$role->save();
        $this->getJson($this->prefix.'channels/'.$role->subdomain)->assertNotFound();
        $this->getJson($this->prefix.'events/'.$id)->assertNotFound();
        $role->is_unlisted=false;$role->email=\App\Services\DemoService::DEMO_EMAIL;$role->save();
        $this->getJson($this->prefix.'channels')->assertJsonCount(0,'data');
        $role->email='studio@example.org';$role->name='EventWillow DEV';$role->save();
        $this->getJson($this->prefix.'channels')->assertJsonCount(0,'data');
        $this->getJson($this->prefix.'events/'.$id)->assertNotFound();
    }
    public function test_date_window_and_pagination_validation(): void {
        $this->fixture();
        $this->getJson($this->prefix.'events?from=2026-01-01&to=2028-01-01')->assertUnprocessable();
        $this->getJson($this->prefix.'events?per_page=101')->assertUnprocessable();
        $this->getJson($this->prefix.'events?from=2026-03-02&to=2026-03-01')->assertUnprocessable();
        $this->getJson($this->prefix.'events?per_page=1')->assertOk()->assertJsonPath('meta.per_page',1);
    }
    public function test_recurrence_exclusions_and_invalid_dates(): void {
        [$role,$event,$id]=$this->fixture(['starts_at'=>'2026-10-12 14:00:00','days_of_week'=>'0100000', 'recurring_frequency'=>'weekly', 'recurring_exclude_dates'=>['2026-10-19']]);
        $event->refresh();
        // Generate expectations from the native recurrence method, then exercise API occurrence selection.
        $expected=[];
        for($d=Carbon::parse('2026-10-12');$d->lte(Carbon::parse('2026-10-31'));$d->addDay()) if($event->matchesDate($d->toDateString(),$role->timezone)) $expected[]=$d->toDateString();
        $r=$this->getJson($this->prefix.'events?from=2026-10-12&to=2026-10-31')->assertOk();
        $this->assertSame($expected,array_column($r->json('data'),'occurrence_date'));
        $this->getJson($this->prefix.'events/'.$id.'?date=2026-10-19')->assertNotFound();
    }
    public function test_event_times_use_schedule_timezone_across_dst(): void {
        [$role,$event,$id]=$this->fixture(['starts_at'=>'2026-10-12 14:00:00','days_of_week'=>'1111111','recurring_frequency'=>'daily']);
        $r=$this->getJson($this->prefix.'events/'.$id.'?date=2026-11-02')->assertOk();
        $this->assertStringContainsString('T10:00:00-05:00',$r->json('data.local_starts_at'));
    }
    public function test_search_filters_and_facets(): void {
        $this->fixture();
        $this->getJson($this->prefix.'events?city=Brooklyn&q=Autumn')->assertOk()->assertJsonCount(1,'data');
        $this->getJson($this->prefix.'events?q=Brooklyn')->assertOk()->assertJsonCount(1,'data');
        $this->getJson($this->prefix.'events?state=CT')->assertOk()->assertJsonCount(1,'data');
        $this->getJson($this->prefix.'events?city=Boston')->assertOk()->assertJsonCount(0,'data');
        $this->getJson($this->prefix.'facets')->assertOk()->assertJsonPath('data.facets.city.0.label','Brooklyn');
    }
    public function test_week_and_later_use_calendar_week_boundaries(): void {
        Carbon::setTestNow(Carbon::parse('2026-10-09 12:00:00', config('app.timezone')));
        try {
            $this->fixture(['starts_at'=>'2026-10-10 18:00:00']);
            $role=Role::where('subdomain','willow-studio')->firstOrFail();
            $this->createEvent($role,['creator_role_id'=>$role->id,'name'=>'Next week','starts_at'=>'2026-10-12 18:00:00']);
            $week=$this->getJson($this->prefix.'events?period=week')->assertOk()->assertJsonCount(1,'data');
            $week->assertJsonPath('meta.from','2026-10-09')->assertJsonPath('meta.to','2026-10-11');
            $this->getJson($this->prefix.'events?period=later')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.name','Next week')->assertJsonPath('meta.from','2026-10-12');
            Carbon::setTestNow(Carbon::parse('2026-10-11 12:00:00', config('app.timezone')));
            $this->getJson($this->prefix.'events?period=later')->assertOk()->assertJsonPath('meta.from','2026-10-12');
        } finally { Carbon::setTestNow(); }
    }
    public function test_category_facet_and_filter_use_native_category_names(): void {
        [$role,$event]=$this->fixture(['category_id'=>1]);
        $category=$event->resolveCategoryName();
        $this->assertNotEmpty($category);
        $this->getJson($this->prefix.'events?category='.rawurlencode($category))->assertOk()->assertJsonCount(1,'data')->assertJsonPath('meta.facets.category.0.label',$category);
        $this->getJson($this->prefix.'events?category=Unmatched')->assertOk()->assertJsonCount(0,'data');
    }
    public function test_popular_feed_omits_zero_views_but_directory_sort_keeps_them(): void {
        $this->fixture();
        $this->getJson($this->prefix.'events?feed=popular')->assertOk()->assertJsonCount(0,'data');
        $this->getJson($this->prefix.'events?sort=popular')->assertOk()->assertJsonCount(1,'data');
    }
    public function test_legacy_schedule_and_group_filter_identifiers_are_supported(): void {
        [$role,$event,$id]=$this->fixture();
        $group=$this->createGroup($role,['name'=>'Circles','slug'=>'circles']);
        $event->roles()->updateExistingPivot($role->id,['group_id'=>$group->id]);
        $this->getJson($this->prefix.'events?schedule=willow-studio&group=willow-studio/circles')->assertOk()->assertJsonCount(1,'data');
        $this->getJson($this->prefix.'events?group='.UrlUtils::encodeId($group->id))->assertOk()->assertJsonCount(1,'data');
    }
    public function test_past_events_respect_channel_setting(): void {
        [$role,$event,$id]=$this->fixture(['starts_at'=>now()->subDays(3)->format('Y-m-d H:i:s')]);
        $date=$event->getStartDateTime(null,true)->format('Y-m-d');
        $this->getJson($this->prefix.'events/'.$id.'?date='.$date)->assertOk();
        $role->hide_past_events=true;$role->save();
        $this->getJson($this->prefix.'events/'.$id.'?date='.$date)->assertNotFound();
    }
    public function test_ongoing_multiday_event_is_one_occurrence(): void {
        [$role,$event,$id]=$this->fixture(['starts_at'=>now()->subDay()->format('Y-m-d H:i:s'),'duration'=>72]);
        $role->hide_past_events=true;$role->save();
        $this->getJson($this->prefix.'events')->assertOk()->assertJsonCount(1,'data');
        $this->getJson($this->prefix.'events/'.$id)->assertOk();
        $this->getJson($this->prefix.'events/'.$id.'?date='.now()->addDay()->toDateString())->assertNotFound();
    }
    public function test_regular_key_cannot_use_site_api_and_site_key_cannot_use_user_api(): void {
        $this->withHeaders(['X-EventWillow-Site-Key'=>'','X-API-Key'=>'fixture-site-key'])->getJson($this->prefix.'events')->assertUnauthorized();
        $this->withHeaders(['X-EventWillow-Site-Key'=>'fixture-site-key','X-API-Key'=>''])->getJson('/api/events')->assertUnauthorized();
    }
    public function test_private_custom_fields_and_unrelated_definitions_are_not_exposed(): void {
        [$role,$event,$id]=$this->fixture();
        $role->event_custom_fields=['public'=>['name'=>'Room','type'=>'string'],'private'=>['name'=>'Access code','type'=>'string','private'=>true]];$role->save();
        $event->custom_field_values=['public'=>'Willow Room','private'=>'sensitive-fixture-value'];$event->custom_field_values_role_id=$role->id;$event->save();
        $response=$this->getJson($this->prefix.'events/'.$id)->assertOk();
        $this->assertStringContainsString('Willow Room',$response->getContent());
        $this->assertStringNotContainsString('sensitive-fixture-value',$response->getContent());
        $event->custom_field_values_role_id=$role->id+100;$event->save();
        $this->getJson($this->prefix.'events/'.$id)->assertOk()->assertJsonPath('data.custom_fields',[]);
    }
    public function test_throttle_has_retry_header(): void {
        config(['eventwillow-public.requests_per_minute'=>1, 'app.is_testing'=>false]);
        $this->getJson($this->prefix.'channels')->assertOk();
        $this->getJson($this->prefix.'channels')->assertStatus(429)->assertHeader('Retry-After');
    }
}
