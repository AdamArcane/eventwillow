<?php
namespace App\Modules\EventWillowPublicApi;

use App\Models\Event;
use App\Models\Role;
use App\Utils\UrlUtils;
use Carbon\Carbon;

require_once __DIR__.'/Location.php';

final class Serializer
{
    public static function channel(Role $role): array
    {
        return [
            'id' => UrlUtils::encodeId($role->id), 'identifier' => $role->subdomain,
            'slug' => \Illuminate\Support\Str::slug($role->name), 'name' => $role->name,
            'description' => strip_tags($role->description ?? ''), 'summary' => $role->short_description ?? '',
            'type' => $role->type, 'timezone' => $role->timezone ?: config('app.timezone'),
            'accent_color' => preg_match('/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/iD', $role->accent_color ?? '') ? $role->accent_color : '#4E81FA',
            'image' => $role->getProfileImageUrl(960), 'header_image' => $role->headerImageUrl(960),
            'location' => self::location($role), 'website' => self::safeUrl($role->website),
            'canonical_url' => $role->getGuestUrl(true), 'hide_past_events' => (bool) $role->hide_past_events,
            'groups' => $role->groups->map(fn ($g) => ['id' => UrlUtils::encodeId($g->id), 'name' => $g->name])->values()->all(),
            'actions' => [['label' => 'Visit channel', 'url' => $role->getGuestUrl(true), 'type' => 'channel']],
        ];
    }

    public static function location(?Role $role): array
    {
        return ['venue' => $role?->name ?? '', 'venue_id' => $role ? UrlUtils::encodeId($role->id) : '',
            'address' => $role?->address1 ?? '', 'city' => $role?->city ?? '', 'state' => stateName($role?->state ?? '', $role?->country_code ?? '', $role?->address1 ?? ''),
            'country' => strtoupper($role?->country_code ?? ''), 'postal' => $role?->postal_code ?? ''];
    }

    public static function safeUrl(?string $url): ?string
    {
        return $url && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?: ''), ['https', 'http'], true) ? $url : null;
    }

    public static function event(Event $event, string $date): array
    {
        $timezone = $event->scheduleTimezone();
        $start = $event->occurrenceStartUtc($date, $timezone);
        $roles = $event->roles;
        $venue = $roles->firstWhere('type', 'venue');
        $home = $roles->first();
        $url = $event->getGuestUrl($home->subdomain, $date, true);
        // Do not let canonicalTarget choose a hidden/unaccepted association outside this public relation.
        $canonical = $url;
        $fields = [];
        $owner = $event->creatorRole;
        if ($owner && $roles->contains('id', $owner->id)) {
            foreach ($event->publicCustomFieldValuesFor($owner) as $key => $value) {
                $definition = $owner->getEventCustomFields()[$key] ?? null;
                if (!$definition) continue;
                $label = $definition['name'] ?? $definition['label'] ?? '';
                if (!$label) continue;
                $fieldKey = 'field_'.substr(hash('sha256', mb_strtolower($label)), 0, 12);
                $fields[$fieldKey] = ['label' => $label, 'values' => array_values(array_map(fn ($v) => strip_tags((string) $v), is_array($value) ? $value : [$value]))];
            }
        }
        // Resolve fallbacks only from the discovery-filtered, accepted relation.
        // Native getImageUrl() considers talent/venue only and can consult other relations.
        $image = $event->flyer_image_url ? $event->getImageUrl(960) : null;
        if (!$image) {
            $imageRoles = $roles;
            if ($owner && $roles->contains('id', $owner->id)) {
                $imageRoles = collect([$roles->firstWhere('id', $owner->id)])->concat($roles->where('id', '!=', $owner->id));
            }
            foreach ($imageRoles as $imageRole) {
                if ($imageRole->profile_image_url) {
                    $image = $imageRole->getProfileImageUrl(960);
                    break;
                }
            }
        }
        $price = $event->ticketPriceSummary($date);
        $registration = $event->tickets_enabled ? 'tickets' : ($event->rsvp_enabled ? 'rsvp' : ($event->registration_url ? 'external' : 'none'));
        $actions = [['label' => 'View on EventWillow', 'url' => $url, 'type' => 'view']];
        if ($registration !== 'none') $actions[] = ['label' => match ($registration) {'tickets' => 'Get tickets', 'rsvp' => 'RSVP', default => 'Register'}, 'url' => $url, 'type' => $registration];
        if (!$home->hide_email_signup) $actions[] = ['label' => 'Follow channel', 'url' => $home->getGuestUrl(true), 'type' => 'follow'];
        $cost = $price ? ($price['free'] ? 'free' : 'paid') : ($event->rsvp_enabled ? 'free' : '');
        return [
            'id' => UrlUtils::encodeId($event->id), 'key' => UrlUtils::encodeId($event->id).':'.$date,
            'slug' => \Illuminate\Support\Str::slug($event->name), 'name' => $event->name,
            'description' => strip_tags($event->description ?? ''),
            'summary' => strip_tags($event->short_description ?: ($event->description ?? '')),
            'image' => $image, 'category' => $event->resolveCategoryName() ?? '',
            'starts_at' => $start->toIso8601String(), 'ends_at' => $start->copy()->addMinutes($event->durationInMinutes())->toIso8601String(),
            'local_starts_at' => $start->copy()->setTimezone($timezone)->toIso8601String(),
            'occurrence_date' => $date, 'timezone' => $timezone, 'recurring' => (bool) $event->days_of_week,
            'recurrence' => $event->recurrenceSummary(), 'location' => self::location($venue),
            'channels' => $roles->map(fn ($r) => self::channel($r))->values()->all(),
            'groups' => $roles->filter(fn ($r) => $r->pivot?->group_id)->map(function ($r) {
                $group=$r->groups->firstWhere('id',$r->pivot->group_id);
                return ['id'=>UrlUtils::encodeId($r->pivot->group_id),'identifier'=>$group ? $r->subdomain.'/'.$group->slug : '', 'name'=>$group?->name ?? ''];
            })->values()->all(),
            'format' => $event->event_url ? 'online' : 'in-person', 'registration' => $registration,
            'price' => $price, 'cost' => $cost, 'custom_fields' => $fields,
            'canonical_url' => $canonical, 'actions' => $actions, 'created_at' => $event->created_at?->toIso8601String(),
        ];
    }
}
