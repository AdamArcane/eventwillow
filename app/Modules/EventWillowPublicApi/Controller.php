<?php
namespace App\Modules\EventWillowPublicApi;

use App\Http\Controllers\Controller as BaseController;
use App\Utils\UrlUtils;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Controller extends BaseController
{
    private function response($data, array $meta = [])
    {
        return response()->json(['data' => $data, 'meta' => array_merge(['api_version' => '1', 'generated_at' => now()->toIso8601String(), 'max_age' => 120], $meta)])
            ->header('Cache-Control', 'private, no-store');
    }

    private function input(Request $request): array
    {
        return $request->validate([
            'q' => 'nullable|string|max:200', 'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d',
            'date' => 'nullable|date_format:Y-m-d', 'page' => 'nullable|integer|min:1|max:100000',
            'per_page' => 'nullable|integer|min:1|max:'.config('eventwillow-public.max_per_page'),
            'mode' => 'nullable|in:series,occurrences', 'feed' => 'nullable|in:upcoming,popular,new',
            'sort' => 'nullable|in:soonest,latest,name,newest,popular',
            'period' => 'nullable|in:upcoming,today,week,later,weekend,month,custom,past',
            'country' => 'nullable|string|max:200', 'state' => 'nullable|string|max:200', 'city' => 'nullable|string|max:200',
            'category' => 'nullable|string|max:200', 'venue' => 'nullable|string|max:200', 'schedule' => 'nullable|string|max:200',
            'group' => 'nullable|string|max:200', 'timezone' => 'nullable|timezone', 'postal' => 'nullable|string|max:30',
            'format' => 'nullable|in:online,in-person', 'cost' => 'nullable|in:free,paid',
            'registration' => 'nullable|in:tickets,rsvp,external,none', 'repeat' => 'nullable|in:single,recurring',
            'custom' => 'nullable|array|max:30', 'custom.*' => 'string|max:200', 'type' => 'nullable|in:venue,talent,curator',
        ]);
    }

    private function range(array $input): array
    {
        $today = Carbon::today(config('app.timezone'));
        $from = $today->copy(); $to = $today->copy()->addDays(365);
        switch ($input['period'] ?? 'upcoming') {
            case 'today': $to = $today->copy(); break;
            case 'week': $to = $today->copy()->endOfWeek(); break;
            case 'later': $from = $today->copy()->addWeek()->startOfWeek(); break;
            case 'weekend': $from = $today->copy()->addDays(max(0, 5 - $today->dayOfWeekIso)); $to = $from->copy()->endOfWeek(); break;
            case 'month': $to = $today->copy()->endOfMonth(); break;
            case 'past': $from = $today->copy()->subDays(365); $to = $today->copy()->subDay(); break;
        }
        $from = isset($input['from']) ? Carbon::parse($input['from']) : $from;
        $to = isset($input['to']) ? Carbon::parse($input['to']) : $to;
        if ($to->lt($from) || $from->diffInDays($to) > config('eventwillow-public.max_range_days')) {
            throw ValidationException::withMessages(['to' => 'The date window must be ordered and no longer than 365 days.']);
        }
        return [$from->format('Y-m-d'), $to->format('Y-m-d')];
    }

    private function resolveChannel(string $identifier)
    {
        $role = Visibility::channels()->where('subdomain', $identifier)->first();
        if (!$role) $role = Visibility::channels()->find(UrlUtils::decodeId($identifier));
        abort_unless($role, 404);
        return $role;
    }

    public function channels(Request $request)
    {
        $in = $this->input($request); $q = Visibility::channels();
        if (!empty($in['q'])) $q->where('name', 'like', '%'.addcslashes($in['q'], '%_').'%' );
        if (!empty($in['type'])) $q->where('type', $in['type']);
        foreach (['country' => 'country_code', 'state' => 'state', 'city' => 'city'] as $key => $column) {
            if (!empty($in[$key])) $q->where($column, $in[$key]);
        }
        $p = $q->orderBy('name')->orderBy('id')->paginate($in['per_page'] ?? 24);
        return $this->response(collect($p->items())->map(fn ($r) => Serializer::channel($r))->all(), ['total' => $p->total(), 'page' => $p->currentPage(), 'per_page' => $p->perPage(), 'last_page' => $p->lastPage()]);
    }

    public function channel(Request $request, string $identifier)
    {
        return $this->response(Serializer::channel($this->resolveChannel($identifier)));
    }

    public function channelEvents(Request $request, string $identifier)
    {
        return $this->listing($request, $this->resolveChannel($identifier));
    }

    public function events(Request $request)
    {
        return $this->listing($request);
    }

    public function facets(Request $request)
    {
        return $this->listing($request, null, true);
    }

    public function event(Request $request, string $id)
    {
        $in = $this->input($request);
        $event = Visibility::events()->find(UrlUtils::decodeId($id));
        abort_unless($event, 404);
        $anchor = $event->getStartDateTime(null, true)->format('Y-m-d');
        $date = $in['date'] ?? ($event->days_of_week ? $event->nextOccurrenceFrom() : $anchor);
        abort_if(!$event->days_of_week && $date !== $anchor, 404);
        abort_unless($date && $event->matchesDate($date, $event->scheduleTimezone()), 404);
        $start = $event->occurrenceStartUtc($date);
        abort_if($start->copy()->addMinutes($event->durationInMinutes())->lt(now()) && $event->roles->every(fn ($r) => (bool) $r->hide_past_events), 404);
        return $this->response(Serializer::event($event, $date));
    }

    private function matches(array $item, array $in): bool
    {
        foreach (['country', 'state', 'city', 'postal'] as $key) {
            if (empty($in[$key])) continue;
            $value = $item['location'][$key];
            if ($key === 'state') $in[$key] = stateName($in[$key], $item['location']['country']);
            if ($key === 'postal' ? mb_stripos($value, $in[$key]) !== 0 : strcasecmp($value, $in[$key]) !== 0) return false;
        }
        foreach (['category', 'timezone', 'format', 'cost', 'registration'] as $key) {
            if (!empty($in[$key]) && strcasecmp((string) $item[$key], $in[$key]) !== 0) return false;
        }
        if (!empty($in['venue']) && $in['venue'] !== $item['location']['venue_id']) return false;
        if (!empty($in['schedule']) && !collect($item['channels'])->contains(fn ($c) => $c['id'] === $in['schedule'] || $c['identifier'] === $in['schedule'])) return false;
        if (!empty($in['group']) && !collect($item['groups'])->contains(fn ($g) => $g['id']===$in['group'] || $g['identifier']===$in['group'])) return false;
        if (!empty($in['repeat']) && $in['repeat'] !== ($item['recurring'] ? 'recurring' : 'single')) return false;
        foreach ($in['custom'] ?? [] as $key => $value) {
            $normalize = fn ($v) => mb_strtolower(trim(preg_replace('/\s+/u',' ', $v)));
            if (!in_array($normalize($value), array_map($normalize, $item['custom_fields'][$key]['values'] ?? []), true)) return false;
        }
        return empty($in['q']) || mb_stripos(implode(' ', [$item['name'], $item['summary'], $item['description'], $item['location']['venue'], $item['location']['city'], $item['location']['state'], $item['category'], implode(' ',array_column($item['channels'],'name'))]), $in['q']) !== false;
    }

    private function listing(Request $request, $channel = null, bool $facetsOnly = false)
    {
        $in = $this->input($request); [$from, $to] = $this->range($in);
        $mode = $in['mode'] ?? ((isset($in['from']) || isset($in['to']) || ($in['period'] ?? 'upcoming') !== 'upcoming') ? 'occurrences' : 'series');
        $sort = $in['sort'] ?? match ($in['feed'] ?? '') {'new' => 'newest', 'popular' => 'popular', default => (($in['period'] ?? '') === 'past' ? 'latest' : 'soonest')};
        $popularFeed = ($in['feed'] ?? '') === 'popular';
        $query = Visibility::events()->where('starts_at', '<=', Carbon::parse($to)->addDays(2)->endOfDay());
        if ($channel) $query->whereHas('roles', fn ($q) => $q->where('roles.id', $channel->id)->where('event_role.is_accepted', true));
        $items = []; $facets = array_fill_keys(['country', 'state', 'city', 'category', 'venue', 'schedule', 'group', 'timezone'], []); $custom = [];
        $views = $sort === 'popular' ? DB::table('analytics_events_daily')->whereBetween('date', [now('UTC')->subDays(29)->toDateString(), now('UTC')->toDateString()])->selectRaw('event_id, SUM(desktop_views + mobile_views + tablet_views + unknown_views) AS views')->groupBy('event_id')->pluck('views', 'event_id') : collect();
        $query->chunkById(100, function ($events) use (&$items, &$facets, &$custom, $in, $from, $to, $mode, $channel, $views, $sort, $popularFeed) {
            foreach ($events as $event) {
                if ($popularFeed && ($views[$event->id] ?? 0) <= 0) continue;
                // Schedule dates, not UTC dates, identify occurrences. Use the application's native matcher.
                $cursor = $event->days_of_week ? Carbon::parse($from) : Carbon::parse($event->getStartDateTime(null, true)->format('Y-m-d'));
                $until = $event->days_of_week ? Carbon::parse($to) : $cursor->copy();
                for (; $cursor->lte($until); $cursor->addDay()) {
                    $date = $cursor->toDateString();
                    if (!$event->matchesDate($date, $event->scheduleTimezone())) continue;
                    $start = $event->occurrenceStartUtc($date);
                    $end = $start->copy()->addMinutes($event->durationInMinutes());
                    if ($date > $to || $end->copy()->setTimezone($event->scheduleTimezone())->toDateString() < $from) continue;
                    $past = $end->lt(now());
                    if ($past && ($channel ? $channel->hide_past_events : $event->roles->every(fn ($r) => $r->hide_past_events))) continue;
                    if (!isset($in['from']) && !isset($in['to']) && ($in['period'] ?? 'upcoming') === 'upcoming' && $past) continue;
                    $item = Serializer::event($event, $date);
                    $item['views_30d'] = (int) ($views[$event->id] ?? 0);
                    foreach (['country','state','city'] as $key) {
                        if ($item['location'][$key]) $facets[$key][$item['location'][$key]] = $key === 'country' ? countryName($item['location'][$key]) : $item['location'][$key];
                    }
                    foreach (['category','timezone'] as $key) if ($item[$key]) $facets[$key][$item[$key]] = $item[$key];
                    if ($item['location']['venue_id']) $facets['venue'][$item['location']['venue_id']] = $item['location']['venue'];
                    foreach ($item['channels'] as $c) $facets['schedule'][$c['id']] = $c['name'];
                    foreach ($item['groups'] as $g) $facets['group'][$g['id']] = $g['name'];
                    foreach ($item['custom_fields'] as $key => $field) {
                        $custom[$key]['label'] = $field['label'];
                        foreach ($field['values'] as $value) $custom[$key]['options'][$value] = $value;
                    }
                    if ($this->matches($item, $in)) $items[] = $item;
                    if ($mode === 'series') break;
                }
            }
        });
        foreach ($facets as &$values) { natcasesort($values); $values = collect($values)->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label])->values()->all(); } unset($values);
        foreach ($custom as &$field) $field['options'] = array_values($field['options']); unset($field);
        $meta = ['from' => $from, 'to' => $to, 'mode' => $mode, 'facets' => $facets, 'custom_fields' => $custom];
        if ($facetsOnly) return $this->response(['facets' => $facets, 'custom_fields' => $custom], $meta);
        usort($items, function ($a, $b) use ($sort) {
            $result = match ($sort) {
                'latest' => strcmp($b['starts_at'], $a['starts_at']), 'name' => strnatcasecmp($a['name'], $b['name']),
                'newest' => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''),
                'popular' => $b['views_30d'] <=> $a['views_30d'], default => strcmp($a['starts_at'], $b['starts_at']),
            };
            return $result ?: strcmp($a['starts_at'], $b['starts_at']) ?: strcmp($a['key'], $b['key']);
        });
        $page = (int) ($in['page'] ?? 1); $perPage = (int) ($in['per_page'] ?? 24); $total = count($items);
        return $this->response(array_slice($items, ($page - 1) * $perPage, $perPage), array_merge($meta, ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'last_page' => max(1, (int) ceil($total / $perPage))]));
    }
}
