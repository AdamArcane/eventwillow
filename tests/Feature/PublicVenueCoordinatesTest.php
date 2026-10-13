<?php

namespace Tests\Feature;

use App\Models\PlaceLookup;
use App\Modules\EventWillowPublicApi\Coordinates;
use App\Modules\EventWillowPublicApi\Serializer;
use App\Services\PlaceLookupService;
use App\Utils\UrlUtils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\CreatesScheduleData;
use Tests\TestCase;

class PublicVenueCoordinatesTest extends TestCase
{
    use CreatesScheduleData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.map.geocoder_url' => 'https://geocoder.invalid/search',
            'eventwillow-public.site_key' => 'fixture-site-key', 'eventwillow-public.enforce_ip_allowlist' => false,
            'app.search_exclude_country' => '']);
        Cache::forget('eventwillow.coordinates.cursor');
        $this->withHeaders(['X-EventWillow-Site-Key' => 'fixture-site-key']);
    }

    private function fixture(array $eventAttributes = []): array
    {
        $venue = $this->createVenueWithAddress($this->createOwner(), ['name' => 'Riverside Hall']);
        $event = $this->createEvent($venue, ['name' => 'Autumn Gathering', 'creator_role_id' => $venue->id] + $eventAttributes);

        return [$venue, $event];
    }

    public function test_public_venue_is_queued_without_a_map_and_cached_coordinates_reach_api(): void
    {
        [$venue,$event] = $this->fixture();
        Coordinates::registerBatch();
        $this->assertDatabaseCount('venue_map_settings', 0);
        $this->assertDatabaseCount('place_lookups', 1);
        Http::assertNothingSent();
        $this->getJson('/api/eventwillow/v1/events/'.UrlUtils::encodeId($event->id))
            ->assertOk()->assertJsonPath('data.location.latitude', null)->assertJsonPath('data.location.geocode_status', 'pending');
        Http::fake(['geocoder.invalid/*' => Http::response([['lat' => '41.76', 'lon' => '-72.67', 'place_rank' => 30]])]);
        $this->assertSame(1, PlaceLookupService::run(4, 0)['asked']);
        Coordinates::registerBatch();
        $this->assertSame(0, PlaceLookupService::run(4, 0)['asked']);
        Http::assertSentCount(1);
        foreach (['events/'.UrlUtils::encodeId($event->id), 'events?from='.now()->toDateString().'&to='.now()->addDays(30)->toDateString()] as $path) {
            $key = str_contains($path, '?') ? 'data.0.location' : 'data.location';
            $this->getJson('/api/eventwillow/v1/'.$path)->assertOk()
                ->assertJsonPath($key.'.latitude', 41.76)->assertJsonPath($key.'.longitude', -72.67)
                ->assertJsonPath($key.'.geocode_status', 'found');
        }
        $venue->update(['address1' => '456 Other Street']);
        $this->getJson('/api/eventwillow/v1/events/'.UrlUtils::encodeId($event->id))
            ->assertOk()->assertJsonPath('data.location.latitude', null)->assertJsonPath('data.location.geocode_status', 'pending');
        Coordinates::registerBatch();
        $this->assertDatabaseCount('place_lookups', 2);
        Http::assertSentCount(1);
    }

    public function test_private_online_and_past_events_are_not_queued(): void
    {
        foreach ([['is_private' => true], ['is_draft' => true], ['is_cancelled' => true], ['is_hidden_from_discovery' => true],
            ['event_url' => 'https://meeting.invalid'], ['starts_at' => now()->subDays(3)]] as $attributes) {
            $this->fixture($attributes);
        }
        Coordinates::registerBatch();
        $this->assertDatabaseCount('place_lookups', 0);
        Http::assertNothingSent();
    }

    public function test_combined_address_and_duplicate_venues_share_one_cache_entry(): void
    {
        [$venue] = $this->fixture();
        $venue->update(['address1' => '80 Wooster Street, Bethel, CT 06801', 'city' => null, 'state' => null, 'postal_code' => null]);
        [$second] = $this->fixture();
        $second->update(['address1' => $venue->address1, 'city' => null, 'state' => null, 'postal_code' => null]);
        Coordinates::registerBatch();
        $this->assertDatabaseCount('place_lookups', 1);
        $this->assertSame('80 Wooster Street, Bethel, CT 06801', PlaceLookup::first()->address);
        PlaceLookup::first()->update(['status' => 'missing', 'looked_up_at' => now()->subDays(31)]);
        Coordinates::registerBatch();
        $this->assertSame('pending', PlaceLookup::first()->status);
        PlaceLookupService::forget([$venue]);
        $this->assertDatabaseCount('place_lookups', 0);
    }

    public function test_unclaimed_accepted_venue_is_queued_but_hidden_venue_is_not(): void
    {
        [$channel,$event] = $this->fixture();
        $channel->update(['type' => 'curator']);
        $venue = $this->createVenueWithAddress($this->createOwner(), ['name' => 'Public Hall']);
        $venue->forceFill(['user_id' => null, 'email' => null, 'email_verified_at' => null])->save();
        $event->roles()->attach($venue->id, ['is_accepted' => true]);
        $venue->update(['is_unlisted' => true]);
        Coordinates::registerBatch();
        $this->assertDatabaseCount('place_lookups', 0);
        $venue->update(['is_unlisted' => false]);
        Coordinates::registerBatch();
        $this->assertDatabaseCount('place_lookups', 1);
    }

    public function test_online_event_never_exports_physical_coordinates_and_google_is_not_used(): void
    {
        [$venue,$event] = $this->fixture(['event_url' => 'https://meeting.invalid']);
        $venue->forceFill(['geo_lat' => 10, 'geo_lon' => 20])->save();
        $this->assertNull(Serializer::location($venue)['latitude']);
        $address = PlaceLookupService::addressFor($venue);
        PlaceLookupService::register([$address]);
        PlaceLookup::first()->update(['status' => 'approximate', 'lat' => 41.76, 'lon' => -72.67]);
        $this->getJson('/api/eventwillow/v1/events/'.UrlUtils::encodeId($event->id))
            ->assertOk()->assertJsonPath('data.location.latitude', null)->assertJsonPath('data.location.geocode_status', 'online');
        $this->assertSame('approximate', Serializer::location($venue->fresh())['geocode_status']);
        Http::assertNothingSent();
    }
}
