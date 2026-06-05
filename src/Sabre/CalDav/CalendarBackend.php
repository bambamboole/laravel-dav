<?php

namespace Bambamboole\LaravelDav\Sabre\CalDav;

use Bambamboole\LaravelDav\Contracts\DavOwner;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Models\DavSchedulingObject;
use Bambamboole\LaravelDav\Parsing\CalendarObjectParser;
use Bambamboole\LaravelDav\Sabre\Concerns\RecordsDavChanges;
use Bambamboole\LaravelDav\Sabre\Concerns\ResolvesPrincipalUri;
use Bambamboole\LaravelDav\Support\DavChangeRecorder;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Backend\SchedulingSupport;
use Sabre\CalDAV\Backend\SyncSupport;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\PropPatch;
use Sabre\DAV\StringUtil;
use Sabre\VObject;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

class CalendarBackend extends AbstractBackend implements SchedulingSupport, SyncSupport
{
    use RecordsDavChanges;
    use ResolvesPrincipalUri;

    private const DisplayNameProperty = '{DAV:}displayname';

    private const DescriptionProperty = '{urn:ietf:params:xml:ns:caldav}calendar-description';

    private const ColorProperty = '{http://apple.com/ns/ical/}calendar-color';

    private const TimezoneProperty = '{urn:ietf:params:xml:ns:caldav}calendar-timezone';

    private const SupportedComponentsProperty = '{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set';

    private const SyncTokenProperty = '{http://sabredav.org/ns}sync-token';

    public function __construct(private CalendarObjectParser $parser) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCalendarsForUser($principalUri): array
    {
        $userId = $this->userIdFromPrincipalUri((string) $principalUri);

        if ($userId === null) {
            return [];
        }

        return Dav::modelFor('calendar', DavCalendar::class)::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get()
            ->map(fn (DavCalendar $calendar): array => $this->calendarRow($calendar))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function createCalendar($principalUri, $calendarUri, array $properties): int
    {
        $userId = $this->userIdFromPrincipalUri((string) $principalUri);

        if ($userId === null || ! $this->ownerExists($userId)) {
            throw new NotFound('Principal not found');
        }

        $calendar = Dav::modelFor('calendar', DavCalendar::class)::query()->create([
            'user_id' => $userId,
            'uri' => (string) $calendarUri,
            'display_name' => (string) ($properties[self::DisplayNameProperty] ?? $calendarUri),
            'description' => $properties[self::DescriptionProperty] ?? null,
            'color' => $properties[self::ColorProperty] ?? null,
            'timezone' => $properties[self::TimezoneProperty] ?? null,
            'components' => $this->componentsFromProperty($properties[self::SupportedComponentsProperty] ?? null),
            'sync_token' => 1,
        ]);

        return (int) $calendar->id;
    }

    public function updateCalendar($calendarId, PropPatch $propPatch): void
    {
        $propPatch->handle([
            self::DisplayNameProperty,
            self::DescriptionProperty,
            self::ColorProperty,
            self::TimezoneProperty,
            self::SupportedComponentsProperty,
        ], function (array $mutations) use ($calendarId): bool {
            $calendar = Dav::model('calendar')::query()->find($calendarId);

            if (! $calendar) {
                return false;
            }

            $values = [];

            foreach ($mutations as $property => $value) {
                match ($property) {
                    self::DisplayNameProperty => $values['display_name'] = $value,
                    self::DescriptionProperty => $values['description'] = $value,
                    self::ColorProperty => $values['color'] = $value,
                    self::TimezoneProperty => $values['timezone'] = $value,
                    self::SupportedComponentsProperty => $values['components'] = $this->componentsFromProperty($value),
                    default => null,
                };
            }

            $calendar->forceFill($values)->save();

            return true;
        });
    }

    public function deleteCalendar($calendarId): void
    {
        Dav::model('calendar')::query()->whereKey($calendarId)->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCalendarObjects($calendarId): array
    {
        return $this->calendar($calendarId)->objects()
            ->select([
                'id',
                'dav_calendar_id',
                'uri',
                'component_type',
                'etag',
                'size',
                'last_modified_at',
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (DavCalendarObject $object): array => $this->objectRow($object))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCalendarObject($calendarId, $objectUri): ?array
    {
        $object = $this->calendar($calendarId)->objects()
            ->where('uri', $objectUri)
            ->first();

        return $object ? $this->objectRow($object, includeData: true) : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, string>
     */
    public function calendarQuery($calendarId, array $filters): array
    {
        $query = $this->calendar($calendarId)->objects()->getQuery()->orderBy('id');

        $this->narrowCalendarQuery($query, $filters);

        return $query->get()
            ->filter(fn (DavCalendarObject $object): bool => $this->objectMatchesCalendarQuery($object, $filters))
            ->pluck('uri')
            ->values()
            ->all();
    }

    /**
     * Narrow the candidate set in SQL using the denormalised columns before the
     * (expensive) parse-and-match in PHP. Deliberately a superset: recurring
     * objects are never excluded by a time-range because their stored
     * starts_at/ends_at only describe the first instance.
     *
     * @param  Builder<DavCalendarObject>  $query
     * @param  array<string, mixed>  $filters
     */
    private function narrowCalendarQuery(Builder $query, array $filters): void
    {
        $compFilters = array_values(array_filter(
            $filters['comp-filters'] ?? [],
            static fn (array $filter): bool => ($filter['name'] ?? '') !== '' && ! ($filter['is-not-defined'] ?? false),
        ));

        $hasNegatedComponent = array_filter(
            $filters['comp-filters'] ?? [],
            static fn (array $filter): bool => (bool) ($filter['is-not-defined'] ?? false),
        ) !== [];

        if ($compFilters !== [] && ! $hasNegatedComponent) {
            $query->whereIn('component_type', array_unique(array_map(
                static fn (array $filter): string => strtoupper((string) $filter['name']),
                $compFilters,
            )));
        }

        if (count($compFilters) === 1 && is_array($compFilters[0]['time-range'] ?? null)) {
            $this->narrowToTimeRange($query, $compFilters[0]['time-range']);
        }
    }

    /**
     * @param  Builder<DavCalendarObject>  $query
     * @param  array{start?: DateTimeInterface|null, end?: DateTimeInterface|null}  $timeRange
     */
    private function narrowToTimeRange(Builder $query, array $timeRange): void
    {
        $start = $timeRange['start'] ?? null;
        $end = $timeRange['end'] ?? null;

        if ($start === null && $end === null) {
            return;
        }

        $query->where(function (Builder $candidate) use ($start, $end): void {
            $candidate->where('recurs', true)
                ->orWhereNull('starts_at')
                ->orWhere(function (Builder $bounded) use ($start, $end): void {
                    if ($end !== null) {
                        $bounded->where('starts_at', '<', $this->utcString($end));
                    }

                    if ($start !== null) {
                        $bounded->where(function (Builder $open) use ($start): void {
                            $open->whereNull('ends_at')->orWhere('ends_at', '>', $this->utcString($start));
                        });
                    }
                });
        });
    }

    private function utcString(DateTimeInterface $dateTime): string
    {
        return CarbonImmutable::instance($dateTime)->utc()->toDateTimeString();
    }

    public function createCalendarObject($calendarId, $objectUri, $calendarData): string
    {
        return '"'.$this->upsertObject($calendarId, (string) $objectUri, (string) $calendarData)->etag.'"';
    }

    public function updateCalendarObject($calendarId, $objectUri, $calendarData): string
    {
        return '"'.$this->upsertObject($calendarId, (string) $objectUri, (string) $calendarData)->etag.'"';
    }

    public function deleteCalendarObject($calendarId, $objectUri): void
    {
        DB::transaction(function () use ($calendarId, $objectUri): void {
            $this->calendar($calendarId)->objects()->where('uri', $objectUri)->first()?->delete();
        });
    }

    private function upsertObject(int|string $calendarId, string $uri, string $payload): DavCalendarObject
    {
        return DB::transaction(function () use ($calendarId, $uri, $payload): DavCalendarObject {
            $calendar = $this->calendar($calendarId);
            $object = $calendar->objects()->firstOrNew(['uri' => $uri]);
            $currentPayload = $object->exists ? $object->calendar_data : null;

            $object->fill([
                'data' => $this->parser->parse($payload, $uri),
                'calendar_data' => $payload,
            ]);

            if (! $this->isSchedulingObject($payload)) {
                $object->schedule_tag = null;
            } elseif ($object->schedule_tag === null || ! $this->isAttendeeParticipantStatusOnlyChange($calendar, $currentPayload, $payload)) {
                $object->schedule_tag = (string) Str::uuid();
            }

            $object->save();

            return $object;
        });
    }

    private function isSchedulingObject(string $payload): bool
    {
        return $this->withCalendar($payload, function (VCalendar $calendar): bool {
            foreach ($this->schedulingComponents($calendar) as $component) {
                if (isset($component->ORGANIZER) && isset($component->ATTENDEE)) {
                    return true;
                }
            }

            return false;
        }) ?? false;
    }

    private function isAttendeeParticipantStatusOnlyChange(DavCalendar $calendar, ?string $currentPayload, string $payload): bool
    {
        if ($currentPayload === null || $currentPayload === '') {
            return false;
        }

        if (! $this->isAttendeeSchedulingObject($calendar, $payload)) {
            return false;
        }

        $current = $this->calendarWithoutParticipantStatus($currentPayload);
        $updated = $this->calendarWithoutParticipantStatus($payload);

        return $current !== null && $current === $updated;
    }

    private function isAttendeeSchedulingObject(DavCalendar $calendar, string $payload): bool
    {
        $owner = $calendar->user;

        if (! $owner instanceof DavOwner || ($email = $owner->getDavPrincipalEmail()) === null) {
            return false;
        }

        return $this->withCalendar($payload, function (VCalendar $parsed) use ($email): bool {
            foreach ($this->schedulingComponents($parsed) as $component) {
                if ($this->componentHasAddress($component, 'ORGANIZER', $email)) {
                    return false;
                }

                if ($this->componentHasAddress($component, 'ATTENDEE', $email)) {
                    return true;
                }
            }

            return false;
        }) ?? false;
    }

    private function componentHasAddress(VObject\Component $component, string $propertyName, string $email): bool
    {
        foreach ($component->select($propertyName) as $property) {
            if ($property instanceof VObject\Property && $this->calendarAddressMatches($property, $email)) {
                return true;
            }
        }

        return false;
    }

    private function calendarAddressMatches(VObject\Property $property, string $email): bool
    {
        return strtolower($property->getValue()) === 'mailto:'.strtolower($email);
    }

    private function calendarWithoutParticipantStatus(string $payload): ?string
    {
        return $this->withCalendar($payload, function (VCalendar $calendar): string {
            foreach ($this->schedulingComponents($calendar) as $component) {
                foreach ($component->select('ORGANIZER') as $organizer) {
                    if ($organizer instanceof VObject\Property) {
                        unset($organizer['SCHEDULE-STATUS'], $organizer['SCHEDULE-FORCE-SEND']);
                    }
                }

                foreach ($component->select('ATTENDEE') as $attendee) {
                    if ($attendee instanceof VObject\Property) {
                        unset($attendee['PARTSTAT'], $attendee['RSVP'], $attendee['SCHEDULE-STATUS'], $attendee['SCHEDULE-FORCE-SEND']);
                    }
                }
            }

            return $this->canonicalCalendarPayload($calendar);
        });
    }

    private function canonicalCalendarPayload(VCalendar $calendar): string
    {
        $lines = preg_split('/\R/', trim($calendar->serialize())) ?: [];
        sort($lines, SORT_STRING);

        return implode("\n", $lines);
    }

    /**
     * @template T
     *
     * @param  callable(VCalendar): T  $callback
     * @return T|null
     */
    private function withCalendar(string $payload, callable $callback)
    {
        try {
            $calendar = Reader::read($payload);
        } catch (\Throwable) {
            return null;
        }

        if (! $calendar instanceof VCalendar) {
            $calendar->destroy();

            return null;
        }

        try {
            return $callback($calendar);
        } finally {
            $calendar->destroy();
        }
    }

    /**
     * @return list<VObject\Component>
     */
    private function schedulingComponents(VCalendar $calendar): array
    {
        return array_values(array_filter(
            $calendar->getBaseComponents(),
            static fn (VObject\Component $component): bool => in_array($component->name, ['VEVENT', 'VTODO'], true),
        ));
    }

    /**
     * @return array{syncToken: string, added: array<int, string>, modified: array<int, string>, deleted: array<int, string>}|null
     */
    public function getChangesForCalendar($calendarId, $syncToken, $syncLevel, $limit = null): ?array
    {
        $calendar = Dav::modelFor('calendar', DavCalendar::class)::query()->find($calendarId);
        $syncToken = (string) $syncToken;

        if (! $calendar) {
            return null;
        }

        if ($syncToken === '') {
            return $this->currentResourceChangeResponse(
                $calendar->sync_token,
                $calendar->objects()->orderBy('id')->getQuery(),
                $limit,
            );
        }

        return $this->changedResourceResponse($calendar, DavChangeRecorder::CalendarCollectionType, $syncToken, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    private function calendarRow(DavCalendar $calendar): array
    {
        $components = $calendar->components ?: ['VEVENT', 'VTODO', 'VJOURNAL'];

        return [
            'id' => $calendar->id,
            'uri' => $calendar->uri,
            'principaluri' => $this->principalUri($calendar->user_id),
            self::DisplayNameProperty => $calendar->display_name,
            self::DescriptionProperty => $calendar->description,
            self::ColorProperty => $calendar->color,
            self::TimezoneProperty => $this->calendarTimezoneProperty($calendar->timezone),
            self::SupportedComponentsProperty => new SupportedCalendarComponentSet($components),
            self::SyncTokenProperty => $this->davSyncToken($calendar->sync_token),
        ];
    }

    /**
     * The CalDAV calendar-timezone property must hold a full iCalendar object
     * containing a VTIMEZONE (RFC 4791 §5.2.2). Sabre reads it verbatim when
     * expanding recurrences, so a bare identifier such as "UTC" would make the
     * VObject parser throw. We only expose values that parse as a VCALENDAR and
     * otherwise leave the property unset so Sabre defaults to UTC.
     */
    private function calendarTimezoneProperty(?string $timezone): ?string
    {
        if ($timezone === null || trim($timezone) === '') {
            return null;
        }

        try {
            $parsed = Reader::read($timezone);
        } catch (\Throwable) {
            return null;
        }

        $isCalendar = $parsed instanceof VCalendar;
        $parsed->destroy();

        return $isCalendar ? $timezone : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function objectRow(DavCalendarObject $object, bool $includeData = false): array
    {
        $row = [
            'id' => $object->id,
            'uri' => $object->uri,
            'lastmodified' => $object->last_modified_at->getTimestamp(),
            'etag' => '"'.$object->etag.'"',
            'calendarid' => $object->dav_calendar_id,
            'size' => $object->size,
            'component' => mb_strtolower((string) $object->component_type),
        ];

        if ($includeData) {
            $row['calendardata'] = $object->calendar_data;
        }

        return $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSchedulingObjects($principalUri): array
    {
        $userId = $this->userIdFromPrincipalUri((string) $principalUri);

        if ($userId === null) {
            return [];
        }

        return $this->schedulingObjectQuery($userId)
            ->orderBy('id')
            ->get()
            ->map(fn (DavSchedulingObject $object): array => $this->schedulingObjectRow($object))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSchedulingObject($principalUri, $objectUri): ?array
    {
        $userId = $this->userIdFromPrincipalUri((string) $principalUri);

        if ($userId === null) {
            return null;
        }

        $object = $this->schedulingObjectQuery($userId)->where('uri', $objectUri)->first();

        return $object instanceof DavSchedulingObject ? $this->schedulingObjectRow($object) : null;
    }

    public function createSchedulingObject($principalUri, $objectUri, $objectData): void
    {
        $userId = $this->userIdFromPrincipalUri((string) $principalUri);

        if ($userId === null) {
            return;
        }

        $payload = is_resource($objectData) ? (string) stream_get_contents($objectData) : (string) $objectData;

        Dav::modelFor('scheduling_object', DavSchedulingObject::class)::query()->updateOrCreate(
            ['user_id' => $userId, 'uri' => (string) $objectUri],
            [
                'calendar_data' => $payload,
                'etag' => sha1($payload),
                'size' => strlen($payload),
                'last_modified_at' => now(),
            ],
        );
    }

    public function deleteSchedulingObject($principalUri, $objectUri): void
    {
        $userId = $this->userIdFromPrincipalUri((string) $principalUri);

        if ($userId === null) {
            return;
        }

        $this->schedulingObjectQuery($userId)->where('uri', $objectUri)->delete();
    }

    /**
     * @return Builder<DavSchedulingObject>
     */
    private function schedulingObjectQuery(int|string $userId): Builder
    {
        /** @var class-string<DavSchedulingObject> $model */
        $model = Dav::modelFor('scheduling_object', DavSchedulingObject::class);

        return $model::query()->where('user_id', $userId);
    }

    /**
     * @return array<string, mixed>
     */
    private function schedulingObjectRow(DavSchedulingObject $object): array
    {
        return [
            'id' => $object->id,
            'uri' => $object->uri,
            'lastmodified' => $object->last_modified_at->getTimestamp(),
            'etag' => '"'.$object->etag.'"',
            'size' => $object->size,
            'calendardata' => $object->calendar_data,
        ];
    }

    private function calendar(int|string $calendarId): DavCalendar
    {
        return Dav::modelFor('calendar', DavCalendar::class)::query()->findOrFail($calendarId);
    }

    private function ownerExists(int $userId): bool
    {
        $model = config('dav.owner_model');

        return $model::query()->whereKey($userId)->exists();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function objectMatchesCalendarQuery(DavCalendarObject $object, array $filters): bool
    {
        if (($filters['name'] ?? null) !== 'VCALENDAR') {
            return false;
        }

        if (! $this->passesComponentTypePreFilter($object, $filters)) {
            return false;
        }

        $vCalendar = Reader::read($object->calendar_data);

        if (! $vCalendar instanceof VCalendar) {
            $vCalendar->destroy();

            return false;
        }

        try {
            return $this->componentMatchesFilter($vCalendar, $filters);
        } finally {
            $vCalendar->destroy();
        }
    }

    /**
     * Cheap pre-filter so we avoid parsing every stored object when the query
     * targets a single component type.
     *
     * @param  array<string, mixed>  $filters
     */
    private function passesComponentTypePreFilter(DavCalendarObject $object, array $filters): bool
    {
        $componentType = strtoupper((string) $object->component_type);

        foreach ($filters['comp-filters'] ?? [] as $filter) {
            if ((bool) ($filter['is-not-defined'] ?? false)) {
                continue;
            }

            $filterName = strtoupper((string) ($filter['name'] ?? ''));

            if ($filterName !== '' && $filterName !== $componentType) {
                return false;
            }
        }

        return true;
    }

    /**
     * Recurrence-aware comp-filter matching following RFC 4791 §9.7.
     *
     * Unlike Sabre's {@see CalendarQueryValidator}, a time-range here is ANDed
     * with sibling prop-filters/comp-filters against the same component, and
     * the relaxed top-level VCALENDAR time-range is honoured.
     *
     * @param  array<string, mixed>  $filter
     * @param  list<VObject\Component>  $recurrenceSet  the master and its
     *                                                  RECURRENCE-ID overrides
     *                                                  for $component, used to
     *                                                  expand recurring objects
     */
    private function componentMatchesFilter(VObject\Component $component, array $filter, array $recurrenceSet = []): bool
    {
        if (($filter['time-range'] ?? false) && ! $this->componentInTimeRange($component, $filter['time-range'], $recurrenceSet)) {
            return false;
        }

        foreach ($filter['comp-filters'] ?? [] as $childFilter) {
            if (! $this->childComponentMatches($component, $childFilter)) {
                return false;
            }
        }

        foreach ($filter['prop-filters'] ?? [] as $propertyFilter) {
            if (! $this->propertyMatchesFilter($component, $propertyFilter)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function childComponentMatches(VObject\Component $parent, array $filter): bool
    {
        $name = (string) ($filter['name'] ?? '');
        $children = array_values(array_filter(
            $parent->select($name),
            static fn (VObject\Node $node): bool => $node instanceof VObject\Component,
        ));

        if ((bool) ($filter['is-not-defined'] ?? false)) {
            return $children === [];
        }

        foreach ($this->groupByUid($children) as $recurrenceSet) {
            if ($this->componentMatchesFilter($recurrenceSet[0], $filter, $recurrenceSet)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Groups components by UID so a recurrence master and its RECURRENCE-ID
     * overrides are evaluated together. Components without a UID each form
     * their own group.
     *
     * @param  list<VObject\Component>  $components
     * @return list<list<VObject\Component>>
     */
    private function groupByUid(array $components): array
    {
        $masters = [];
        $overrides = [];
        $ungrouped = [];

        foreach ($components as $component) {
            $uid = isset($component->UID) ? (string) $component->UID : '';

            if ($uid === '') {
                $ungrouped[] = [$component];

                continue;
            }

            if (isset($component->{'RECURRENCE-ID'})) {
                $overrides[$uid][] = $component;
            } else {
                $masters[$uid][] = $component;
            }
        }

        $groups = [];

        foreach ($masters as $uid => $components) {
            $groups[] = [...$components, ...($overrides[$uid] ?? [])];
            unset($overrides[$uid]);
        }

        foreach ($overrides as $components) {
            $groups[] = $components;
        }

        return [...$groups, ...$ungrouped];
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function propertyMatchesFilter(VObject\Component $component, array $filter): bool
    {
        $name = (string) ($filter['name'] ?? '');
        $properties = $component->select($name);

        if ((bool) ($filter['is-not-defined'] ?? false)) {
            return $properties === [];
        }

        if ($properties === []) {
            return false;
        }

        $textMatch = $filter['text-match'] ?? null;
        $paramFilters = $filter['param-filters'] ?? [];

        if ($textMatch === null && $paramFilters === []) {
            return true;
        }

        foreach ($properties as $property) {
            if (! $property instanceof VObject\Property) {
                continue;
            }

            if ($textMatch !== null && ! $this->textMatches((string) $property->getValue(), $textMatch)) {
                continue;
            }

            if ($paramFilters !== [] && ! $this->parametersMatch($property, $paramFilters)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $paramFilters
     */
    private function parametersMatch(VObject\Property $property, array $paramFilters): bool
    {
        foreach ($paramFilters as $paramFilter) {
            $name = (string) ($paramFilter['name'] ?? '');
            $parameter = $property[$name] ?? null;

            if ((bool) ($paramFilter['is-not-defined'] ?? false)) {
                if ($parameter !== null) {
                    return false;
                }

                continue;
            }

            if (! $parameter instanceof VObject\Parameter) {
                return false;
            }

            $textMatch = $paramFilter['text-match'] ?? null;

            if ($textMatch !== null && ! $this->textMatches((string) $parameter->getValue(), $textMatch)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{value: string, negate-condition?: bool, collation?: string}  $textMatch
     */
    private function textMatches(string $value, array $textMatch): bool
    {
        $matches = StringUtil::textMatch(
            $value,
            $textMatch['value'],
            $textMatch['collation'] ?? 'i;ascii-casemap',
        );

        return (bool) ($textMatch['negate-condition'] ?? false) ? ! $matches : $matches;
    }

    /**
     * @param  array{start?: DateTimeInterface|null, end?: DateTimeInterface|null}  $timeRange
     * @param  list<VObject\Component>  $recurrenceSet
     */
    private function componentInTimeRange(VObject\Component $component, array $timeRange, array $recurrenceSet = []): bool
    {
        $start = $timeRange['start'] ?? new \DateTime('1900-01-01');
        $end = $timeRange['end'] ?? new \DateTime('3000-01-01');

        if ($component instanceof VCalendar) {
            $baseComponents = array_values(array_filter(
                $component->getBaseComponents(),
                static fn (VObject\Component $base): bool => $base->name !== 'VTIMEZONE',
            ));

            foreach ($this->groupByUid($baseComponents) as $group) {
                if ($this->recurrenceSetInTimeRange($group, $start, $end)) {
                    return true;
                }
            }

            return false;
        }

        return $this->recurrenceSetInTimeRange(
            $recurrenceSet === [] ? [$component] : $recurrenceSet,
            $start,
            $end,
        );
    }

    /**
     * Expansion-aware time-range check for a single component and, when it
     * recurs, its instances. VObject only expands recurring VEVENTs natively,
     * so we drive the {@see VObject\Recur\EventIterator} ourselves to also
     * cover recurring VTODO/VJOURNAL objects (RFC 4791 §9.9). RECURRENCE-ID
     * overrides are honoured because the iterator receives the whole set.
     *
     * @param  list<VObject\Component>  $recurrenceSet
     */
    private function recurrenceSetInTimeRange(array $recurrenceSet, DateTimeInterface $start, DateTimeInterface $end): bool
    {
        $master = $recurrenceSet[0];

        if (! $this->isRecurring($recurrenceSet)) {
            return $this->nodeInTimeRange($master, $start, $end);
        }

        // VObject caps recurrence expansion (default 3500) to guard against
        // runaway RRULEs. fast-forwarding an infinite recurrence to a far-future
        // window (e.g. a daily event queried decades out) can exceed that, so we
        // raise the cap to a generous finite bound — enough for a daily rule over
        // ~135 years, while still stopping pathological sub-daily rules from
        // hanging the iterator (which then fall back to the master instance).
        $previousMax = VObject\Settings::$maxRecurrences;
        VObject\Settings::$maxRecurrences = 50000;

        try {
            $iterator = new VObject\Recur\EventIterator($recurrenceSet, null, $start instanceof \DateTime ? $start->getTimezone() : null);

            $iterator->fastForward($start);

            while ($iterator->valid() && $iterator->getDtStart() < $end) {
                if ($iterator->getDtEnd() > $start) {
                    return true;
                }

                $iterator->next();
            }

            return false;
        } catch (\Exception) {
            return $this->nodeInTimeRange($master, $start, $end);
        } finally {
            VObject\Settings::$maxRecurrences = $previousMax;
        }
    }

    /**
     * @param  list<VObject\Component>  $recurrenceSet
     */
    private function isRecurring(array $recurrenceSet): bool
    {
        foreach ($recurrenceSet as $component) {
            if (isset($component->RRULE) || isset($component->RDATE) || isset($component->{'RECURRENCE-ID'})) {
                return true;
            }
        }

        return false;
    }

    private function nodeInTimeRange(VObject\Node $node, DateTimeInterface $start, DateTimeInterface $end): bool
    {
        if (! method_exists($node, 'isInTimeRange')) {
            return false;
        }

        return $node->isInTimeRange($start, $end);
    }

    /**
     * @return array<int, string>
     */
    private function componentsFromProperty(mixed $value): array
    {
        if ($value instanceof SupportedCalendarComponentSet) {
            return $value->getValue();
        }

        return ['VEVENT', 'VTODO', 'VJOURNAL'];
    }
}
