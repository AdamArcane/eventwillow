<?php

namespace App\Modules\EventWillowPublicApi;

use App\Models\Role;
use App\Services\PlaceLookupService;
use Illuminate\Support\Facades\Cache;

/** Public discovery uses the shared address cache, never map-specific pin overrides. */
final class Coordinates
{
    public static function preload(iterable $venues): void
    {
        $venues = collect($venues)->filter();
        $addresses = $venues->map(fn ($venue) => PlaceLookupService::addressFor($venue));
        $rows = PlaceLookupService::enabled() ? PlaceLookupService::rows($addresses->pluck('hash')->all()) : collect();
        foreach ($venues as $key => $venue) {
            $address = $addresses[$key];
            $row = $rows->get($address['hash']);
            $status = ! PlaceLookupService::enabled() ? 'disabled' : ($address['why'] ?? $row?->status ?? 'pending');
            $valid = in_array($status, ['found', 'approximate'], true)
                && is_numeric($row?->lat) && is_numeric($row?->lon)
                && abs($row->lat) <= 90 && abs($row->lon) <= 180
                && ! ($row->lat == 0 && $row->lon == 0);
            $venue->setRelation('publicCoordinates', [
                'latitude' => $valid ? (float) $row->lat : null,
                'longitude' => $valid ? (float) $row->lon : null,
                'geocode_status' => $valid || ! in_array($status, ['found', 'approximate'], true) ? $status : 'missing',
            ]);
        }
    }

    public static function location(?Role $venue): array
    {
        if (! $venue) {
            return ['latitude' => null, 'longitude' => null, 'geocode_status' => 'no_venue'];
        }
        if (! $venue->relationLoaded('publicCoordinates')) {
            self::preload([$venue]);
        }

        return $venue->getRelation('publicCoordinates');
    }

    public static function preloadEvents(iterable $events): void
    {
        $venues = [];
        foreach ($events as $event) {
            foreach ($event->roles as $role) {
                $venues[] = $role;
            }
            foreach ($event->getRelation('venue') ?? [] as $role) {
                $venues[] = $role;
            }
        }
        self::preload($venues);
    }

    /** Bounded rolling scan also backfills existing public events without enabling a map. */
    public static function registerBatch(): void
    {
        if (! PlaceLookupService::enabled() || ! config('eventwillow-public.site_key')) {
            return;
        }
        $cursor = (int) Cache::get('eventwillow.coordinates.cursor', 0);
        $events = Visibility::events()->where('events.id', '>', $cursor)
            ->where('starts_at', '<=', now()->addDays(365))
            ->where(fn ($q) => $q->whereNull('event_url')->orWhere('event_url', ''))
            ->orderBy('events.id')->limit(100)->get();
        $addresses = [];
        foreach ($events as $event) {
            if ($event->days_of_week) {
                if (! $event->nextOccurrenceFrom()) {
                    continue;
                }
            } elseif ($event->getStartDateTime(null, true)->addMinutes($event->durationInMinutes())->lt(now())) {
                continue;
            }
            $venue = $event->roles->firstWhere('type', 'venue') ?? $event->getRelation('venue')->first();
            if (! $venue) {
                continue;
            }
            $address = PlaceLookupService::addressFor($venue);
            if ($address['hash']) {
                $addresses[$address['hash']] = $address;
            }
        }
        $rows = PlaceLookupService::rows(array_keys($addresses));
        PlaceLookupService::register(array_diff_key($addresses, $rows->all()));
        PlaceLookupService::stillNeeded($rows);
        foreach ($rows as $row) {
            PlaceLookupService::requeueIfStale($row);
        }
        Cache::put('eventwillow.coordinates.cursor', $events->count() === 100 ? $events->last()->id : 0, now()->addDay());
    }
}
