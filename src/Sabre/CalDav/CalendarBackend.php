<?php

namespace Bambamboole\LaravelDav\Sabre\CalDav;

use Bambamboole\LaravelDav\LaravelDav;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Sabre\Concerns\RecordsDavChanges;
use Bambamboole\LaravelDav\Sabre\Concerns\ResolvesPrincipalUri;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Backend\SyncSupport;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\PropPatch;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

class CalendarBackend extends AbstractBackend implements SyncSupport
{
    use RecordsDavChanges;
    use ResolvesPrincipalUri;

    private const DisplayNameProperty = '{DAV:}displayname';

    private const DescriptionProperty = '{urn:ietf:params:xml:ns:caldav}calendar-description';

    private const ColorProperty = '{http://apple.com/ns/ical/}calendar-color';

    private const TimezoneProperty = '{urn:ietf:params:xml:ns:caldav}calendar-timezone';

    private const SupportedComponentsProperty = '{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set';

    private const SyncTokenProperty = '{http://sabredav.org/ns}sync-token';

    public function __construct(private UpsertCalendarObject $upsertCalendarObject) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCalendarsForUser($principalUri): array
    {
        $userId = $this->userIdFromPrincipalUri((string) $principalUri);

        if ($userId === null) {
            return [];
        }

        return LaravelDav::modelFor('calendar', DavCalendar::class)::query()
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

        $calendar = LaravelDav::modelFor('calendar', DavCalendar::class)::query()->create([
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
            $calendar = LaravelDav::model('calendar')::query()->find($calendarId);

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
        LaravelDav::model('calendar')::query()->whereKey($calendarId)->delete();
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
        return $this->calendar($calendarId)->objects()
            ->orderBy('id')
            ->get()
            ->filter(fn (DavCalendarObject $object): bool => $this->objectMatchesCalendarQuery($object, $filters))
            ->pluck('uri')
            ->values()
            ->all();
    }

    public function createCalendarObject($calendarId, $objectUri, $calendarData): string
    {
        $object = DB::transaction(function () use ($calendarId, $objectUri, $calendarData): DavCalendarObject {
            $calendar = $this->calendar($calendarId);
            $object = $this->upsertCalendarObject->handle(
                $calendar,
                (string) $objectUri,
                (string) $calendarData,
            );

            $this->recordCalendarChange($calendar, (string) $objectUri, self::OperationAdd);

            return $object;
        });

        return '"'.$object->etag.'"';
    }

    public function updateCalendarObject($calendarId, $objectUri, $calendarData): string
    {
        $object = DB::transaction(function () use ($calendarId, $objectUri, $calendarData): DavCalendarObject {
            $calendar = $this->calendar($calendarId);
            $object = $this->upsertCalendarObject->handle(
                $calendar,
                (string) $objectUri,
                (string) $calendarData,
            );

            $this->recordCalendarChange($calendar, (string) $objectUri, self::OperationModify);

            return $object;
        });

        return '"'.$object->etag.'"';
    }

    public function deleteCalendarObject($calendarId, $objectUri): void
    {
        DB::transaction(function () use ($calendarId, $objectUri): void {
            $calendar = $this->calendar($calendarId);
            $deleted = $calendar->objects()
                ->where('uri', $objectUri)
                ->delete();

            if ($deleted > 0) {
                $this->recordCalendarChange($calendar, (string) $objectUri, self::OperationDelete);
            }
        });
    }

    /**
     * @return array{syncToken: string, added: array<int, string>, modified: array<int, string>, deleted: array<int, string>}|null
     */
    public function getChangesForCalendar($calendarId, $syncToken, $syncLevel, $limit = null): ?array
    {
        $calendar = LaravelDav::modelFor('calendar', DavCalendar::class)::query()->find($calendarId);
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

        return $this->changedResourceResponse($calendar, self::CalendarCollectionType, $syncToken, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    private function calendarRow(DavCalendar $calendar): array
    {
        $components = $calendar->components ?: ['VEVENT', 'VTODO'];

        return [
            'id' => $calendar->id,
            'uri' => $calendar->uri,
            'principaluri' => $this->principalUri($calendar->user_id),
            self::DisplayNameProperty => $calendar->display_name,
            self::DescriptionProperty => $calendar->description,
            self::ColorProperty => $calendar->color,
            self::TimezoneProperty => $calendar->timezone,
            self::SupportedComponentsProperty => new SupportedCalendarComponentSet($components),
            self::SyncTokenProperty => $this->davSyncToken($calendar->sync_token),
        ];
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

    private function calendar(int|string $calendarId): DavCalendar
    {
        return LaravelDav::modelFor('calendar', DavCalendar::class)::query()->findOrFail($calendarId);
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

        foreach ($filters['comp-filters'] ?? [] as $filter) {
            if (! $this->objectMatchesComponentFilter($object, $filter)) {
                return false;
            }
        }

        if (($filters['time-range'] ?? false) && ! $this->objectOverlapsTimeRange($object, $filters['time-range'])) {
            return false;
        }

        foreach ($filters['prop-filters'] ?? [] as $filter) {
            if (! $this->objectMatchesPropertyFilter($object, $filter)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function objectMatchesComponentFilter(DavCalendarObject $object, array $filter): bool
    {
        $componentType = strtoupper((string) $object->component_type);
        $filterName = strtoupper((string) ($filter['name'] ?? ''));
        $isDefined = $componentType === $filterName;

        if ((bool) ($filter['is-not-defined'] ?? false)) {
            return ! $isDefined;
        }

        if (! $isDefined) {
            return false;
        }

        if (($filter['time-range'] ?? false) && ! $this->objectOverlapsTimeRange($object, $filter['time-range'])) {
            return false;
        }

        foreach ($filter['prop-filters'] ?? [] as $propertyFilter) {
            if (! $this->objectMatchesPropertyFilter($object, $propertyFilter)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{start?: DateTimeInterface|null, end?: DateTimeInterface|null}  $timeRange
     */
    private function objectOverlapsTimeRange(DavCalendarObject $object, array $timeRange): bool
    {
        $startsAt = $object->starts_at;
        $endsAt = $object->ends_at ?? $startsAt;

        if ($startsAt === null) {
            return false;
        }

        $rangeStart = $timeRange['start'] ?? null;
        $rangeEnd = $timeRange['end'] ?? null;

        if ($rangeStart !== null && $endsAt !== null && $endsAt <= $rangeStart) {
            return false;
        }

        if ($rangeEnd !== null && $startsAt >= $rangeEnd) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function objectMatchesPropertyFilter(DavCalendarObject $object, array $filter): bool
    {
        $value = $this->propertyValue($object, (string) ($filter['name'] ?? ''));
        $isDefined = filled($value);

        if ((bool) ($filter['is-not-defined'] ?? false)) {
            return ! $isDefined;
        }

        if (! $isDefined) {
            return false;
        }

        if (($filter['text-match'] ?? null) !== null && ! $this->textMatches((string) $value, $filter['text-match'])) {
            return false;
        }

        return true;
    }

    private function propertyValue(DavCalendarObject $object, string $property): ?string
    {
        return match (strtoupper($property)) {
            'UID' => $object->uid,
            'SUMMARY' => $object->summary,
            'DESCRIPTION' => $object->description,
            'LOCATION' => $object->location,
            'STATUS' => $object->status,
            'URL' => $object->url,
            'CATEGORIES' => $this->rawPropertyValue($object, 'CATEGORIES'),
            default => null,
        };
    }

    private function rawPropertyValue(DavCalendarObject $object, string $property): ?string
    {
        $vCalendar = Reader::read($object->calendar_data);

        if (! $vCalendar instanceof VCalendar) {
            $vCalendar->destroy();

            return null;
        }

        try {
            foreach ($vCalendar->getBaseComponents() as $component) {
                if ($object->component_type !== null && $component->name !== $object->component_type) {
                    continue;
                }

                if (! isset($component->{$property})) {
                    continue;
                }

                $values = [];

                foreach ($component->{$property} as $value) {
                    $values[] = (string) $value;
                }

                return implode(',', $values);
            }

            return null;
        } finally {
            $vCalendar->destroy();
        }
    }

    /**
     * @param  array{value: string, negate-condition?: bool, collation?: string}  $textMatch
     */
    private function textMatches(string $value, array $textMatch): bool
    {
        $needle = $textMatch['value'];
        $matches = str_contains(mb_strtolower($value), mb_strtolower($needle));

        return (bool) ($textMatch['negate-condition'] ?? false) ? ! $matches : $matches;
    }

    /**
     * @return array<int, string>
     */
    private function componentsFromProperty(mixed $value): array
    {
        if ($value instanceof SupportedCalendarComponentSet) {
            return $value->getValue();
        }

        return ['VEVENT', 'VTODO'];
    }
}
