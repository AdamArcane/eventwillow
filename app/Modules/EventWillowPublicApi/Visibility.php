<?php
namespace App\Modules\EventWillowPublicApi;

use App\Models\Event;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;

final class Visibility
{
    public static function roles($query)
    {
        return self::locationRoles($query)->whereNotNull('user_id')
            ->where(fn ($q) => $q->where(fn ($q) => $q->whereNotNull('email')->whereNotNull('email_verified_at'))
                ->orWhere(fn ($q) => $q->whereNotNull('phone')->whereNotNull('phone_verified_at')));
    }

    // Accepted event locations can be unclaimed without becoming discoverable channels.
    public static function locationRoles($query)
    {
        $query->where('is_deleted', false)->where('is_unlisted', false)
            ->whereNot(fn ($q) => $q->demoContent())
            ->whereRaw('LOWER(TRIM(roles.name)) NOT REGEXP ?', [Event::LIKELY_TEST_NAME_REGEX])
            ->whereRaw('LOWER(TRIM(roles.name)) NOT REGEXP ?', [config('eventwillow-public.test_content_pattern')]);
        $country = strtolower(trim((string) config('app.search_exclude_country', '')));
        if ($country !== '') $query->whereNotNull('country_code')->where('country_code','!=','')->whereRaw('LOWER(country_code) != ?',[$country]);
        return $query;
    }

    public static function channels(): Builder
    {
        return self::roles(Role::query())->with('groups')->withCount([
            'accountOnlyFollowers as account_followers_count',
            'subscribers as confirmed_email_subscribers_count' => fn ($query) => $query->confirmed()
                ->whereNotExists(function ($suppressed) {
                    $suppressed->selectRaw('1')
                        ->from('newsletter_unsubscribes')
                        ->whereColumn('newsletter_unsubscribes.role_id', 'role_subscribers.role_id')
                        ->whereColumn('newsletter_unsubscribes.email', 'role_subscribers.email');
                }),
        ]);
    }

    public static function events(): Builder
    {
        $query = Event::query()->where('is_draft', false)->where('is_private', false)
            ->where('is_internal', false)->where('is_cancelled', false)->where('is_hidden_from_discovery', false)
            ->whereNull('appointment_type_id')->whereNotNull('starts_at')->notPasswordProtected()->excludeLikelyTest()
            ->whereRaw('LOWER(TRIM(events.name)) NOT REGEXP ?', [config('eventwillow-public.test_content_pattern')])
            ->whereHas('roles', fn ($q) => self::roles($q)->where('event_role.is_accepted', true))
            ->whereDoesntHave('roles', fn ($q) => $q->demoContent())
            ->with(['roles' => fn ($q) => self::roles($q)->where('event_role.is_accepted', true)->orderBy('event_role.id'),
                'venue' => fn ($q) => self::locationRoles($q)->where('event_role.is_accepted', true)->orderBy('event_role.id'),
                'roles.groups', 'creatorRole', 'tickets']);
        $country = strtolower(trim((string) config('app.search_exclude_country', '')));
        if ($country !== '') {
            $query->whereHas('roles', fn ($q) => $q->whereNotNull('country_code')->where('country_code', '!=', ''))
                ->whereDoesntHave('roles', fn ($q) => $q->whereRaw('LOWER(country_code) = ?', [$country]));
        }
        return $query;
    }
}
