<?php

namespace Bambamboole\LaravelDav\Sabre\CalDav;

use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarInstance;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Models\DavCalendarSubscription;
use Bambamboole\LaravelDav\Models\DavSchedulingObject;
use Bambamboole\LaravelDav\Sabre\Concerns\RecordsDavChanges;
use Bambamboole\LaravelDav\Sabre\Concerns\ResolvesPrincipalUri;
use Bambamboole\LaravelDav\Support\DavChangeRecorder;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Backend\SchedulingSupport;
use Sabre\CalDAV\Backend\SharingSupport;
use Sabre\CalDAV\Backend\SubscriptionSupport;
use Sabre\CalDAV\Backend\SyncSupport;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Exception\NotImplemented;
use Sabre\DAV\PropPatch;
use Sabre\DAV\Sharing\Plugin as SharingPlugin;
use Sabre\DAV\StringUtil;
use Sabre\DAV\Xml\Element\Sharee;
use Sabre\DAV\Xml\Property\Href;
use Sabre\VObject;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

class CalendarBackend extends AbstractBackend implements SchedulingSupport, SharingSupport, SubscriptionSupport, SyncSupport
{
    use RecordsDavChanges;
    use ResolvesPrincipalUri;

    private const DisplayNameProperty = '{DAV:}displayname';

    private const DescriptionProperty = '{urn:ietf:params:xml:ns:caldav}calendar-description';

    private const ColorProperty = '{http://apple.com/ns/ical/}calendar-color';

    private const TimezoneProperty = '{urn:ietf:params:xml:ns:caldav}calendar-timezone';

    private const SupportedComponentsProperty = '{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set';

    private const SyncTokenProperty = '{http://sabredav.org/ns}sync-token';

    private const SubscriptionSourceProperty = '{http://calendarserver.org/ns/}source';

    private const SubscriptionRefreshRateProperty = '{http://apple.com/ns/ical/}refreshrate';

    private const SubscriptionOrderProperty = '{http://apple.com/ns/ical/}calendar-order';

    private const SubscriptionStripTodosProperty = '{http://calendarserver.org/ns/}subscribed-strip-todos';

    private const SubscriptionStripAlarmsProperty = '{http://calendarserver.org/ns/}subscribed-strip-alarms';

    private const SubscriptionStripAttachmentsProperty = '{http://calendarserver.org/ns/}subscribed-strip-attachments';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCalendarsForUser($principalUri): array
    {
        $userId = $this->userIdFromPrincipalUri((string) $principalUri);

        if ($userId === null) {
            return [];
        }

        return Dav::model(DavCalendarInstance::class)::query()
            ->with('calendar')
            ->whereHas('calendar')
            ->where('owner_id', $userId)
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(fn (DavCalendarInstance $instance): array => $this->calendarRow($instance))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array{0: int, 1: int}
     */
    public function createCalendar($principalUri, $calendarUri, array $properties): array
    {
        $userId = $this->userIdFromPrincipalUri((string) $principalUri);

        if ($userId === null || ! $this->ownerExists($userId)) {
            throw new NotFound('Principal not found');
        }

        $calendar = Dav::model(DavCalendar::class)::createForOwner($userId, [
            'uri' => (string) $calendarUri,
            'components' => $this->componentsFromProperty($properties[self::SupportedComponentsProperty] ?? null),
            'display_name' => (string) ($properties[self::DisplayNameProperty] ?? $calendarUri),
            'description' => $properties[self::DescriptionProperty] ?? null,
            'color' => $properties[self::ColorProperty] ?? null,
            'timezone' => $properties[self::TimezoneProperty] ?? null,
        ]);
        $instance = $calendar->ownerInstance()->firstOrFail();

        return [(int) $calendar->id, (int) $instance->id];
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
            $calendar = $this->calendar($calendarId);
            $instance = $this->calendarInstance($calendarId) ?? $calendar->ownerInstance()->first();

            if (! $instance) {
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

            $instance->updateDavProperties($values);

            return true;
        });
    }

    public function deleteCalendar($calendarId): void
    {
        $instance = $this->calendarInstance($calendarId);

        if ($instance !== null) {
            $instance->deleteDavCollection();

            return;
        }

        $this->calendar($calendarId)->ownerInstance()->first()?->deleteDavCollection();
    }

    /**
     * @param  array<int, Sharee>  $sharees
     */
    public function updateInvites($calendarId, array $sharees): void
    {
        $calendar = $this->calendar($calendarId);
        $sourceInstance = $this->calendarInstance($calendarId) ?? $calendar->ownerInstance()->first();

        if (! $sourceInstance) {
            throw new NotFound('Calendar instance not found');
        }

        foreach ($sharees as $sharee) {
            if ($sharee->access === SharingPlugin::ACCESS_NOACCESS) {
                $calendar->unshareByHref($sharee->href);

                continue;
            }

            $shareeOwnerId = $sharee->principal ? $this->userIdFromPrincipalUri($sharee->principal) : null;

            if ($shareeOwnerId === null || ! $this->ownerExists($shareeOwnerId)) {
                continue;
            }

            $shareDisplayName = $sharee->properties[self::DisplayNameProperty] ?? null;
            $calendar->shareWith($shareeOwnerId, $sharee->access, $sharee->href, $shareDisplayName);
        }
    }

    /**
     * @return array<int, Sharee>
     */
    public function getInvites($calendarId): array
    {
        return Dav::model(DavCalendarInstance::class)::query()
            ->where('dav_calendar_id', $this->calendarKey($calendarId))
            ->orderBy('id')
            ->get()
            ->map(function (DavCalendarInstance $instance): Sharee {
                $principalUri = $this->principalUri($instance->owner_id);

                return new Sharee([
                    'href' => $instance->share_href ?? $principalUri,
                    'principal' => $principalUri,
                    'access' => $instance->access,
                    'inviteStatus' => $instance->share_invite_status ?? 0,
                    'properties' => $instance->share_display_name !== null
                        ? [self::DisplayNameProperty => $instance->share_display_name]
                        : [],
                ]);
            })
            ->all();
    }

    public function setPublishStatus($calendarId, $value): void
    {
        throw new NotImplemented('Publishing calendars is not implemented');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSubscriptionsForUser($principalUri): array
    {
        $userId = $this->userIdFromPrincipalUri((string) $principalUri);

        if ($userId === null) {
            return [];
        }

        return $this->subscriptionQuery($userId)
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(fn (DavCalendarSubscription $subscription): array => $this->subscriptionRow($subscription))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function createSubscription($principalUri, $uri, array $properties): int|string
    {
        $userId = $this->userIdFromPrincipalUri((string) $principalUri);

        if ($userId === null || ! $this->ownerExists($userId)) {
            throw new NotFound('Principal not found');
        }

        $source = $this->subscriptionHref($properties[self::SubscriptionSourceProperty] ?? null);

        if ($source === null) {
            throw new Forbidden('The {http://calendarserver.org/ns/}source property is required when creating subscriptions');
        }

        $subscription = Dav::model(DavCalendarSubscription::class)::query()->create([
            'owner_id' => $userId,
            'uri' => (string) $uri,
            'source' => $source,
            'display_name' => (string) ($properties[self::DisplayNameProperty] ?? $uri),
            'color' => $properties[self::ColorProperty] ?? null,
            'refresh_rate' => $properties[self::SubscriptionRefreshRateProperty] ?? null,
            'order' => (int) ($properties[self::SubscriptionOrderProperty] ?? 0),
            'strip_todos' => $this->subscriptionFlag($properties, self::SubscriptionStripTodosProperty),
            'strip_alarms' => $this->subscriptionFlag($properties, self::SubscriptionStripAlarmsProperty),
            'strip_attachments' => $this->subscriptionFlag($properties, self::SubscriptionStripAttachmentsProperty),
            'last_modified_at' => now(),
        ]);

        return $subscription->getKey();
    }

    public function updateSubscription($subscriptionId, PropPatch $propPatch): void
    {
        $propPatch->handle([
            self::SubscriptionSourceProperty,
            self::DisplayNameProperty,
            self::ColorProperty,
            self::SubscriptionRefreshRateProperty,
            self::SubscriptionOrderProperty,
            self::SubscriptionStripTodosProperty,
            self::SubscriptionStripAlarmsProperty,
            self::SubscriptionStripAttachmentsProperty,
        ], function (array $mutations) use ($subscriptionId): bool {
            $subscription = Dav::model(DavCalendarSubscription::class)::query()->find($subscriptionId);

            if (! $subscription instanceof DavCalendarSubscription) {
                return false;
            }

            $values = [];

            foreach ($mutations as $property => $value) {
                match ($property) {
                    self::SubscriptionSourceProperty => $values['source'] = $this->subscriptionHref($value),
                    self::DisplayNameProperty => $values['display_name'] = $value,
                    self::ColorProperty => $values['color'] = $value,
                    self::SubscriptionRefreshRateProperty => $values['refresh_rate'] = $value,
                    self::SubscriptionOrderProperty => $values['order'] = (int) $value,
                    self::SubscriptionStripTodosProperty => $values['strip_todos'] = $value !== null,
                    self::SubscriptionStripAlarmsProperty => $values['strip_alarms'] = $value !== null,
                    self::SubscriptionStripAttachmentsProperty => $values['strip_attachments'] = $value !== null,
                    default => null,
                };
            }

            if (array_key_exists('source', $values) && $values['source'] === null) {
                return false;
            }

            $subscription->forceFill([
                ...$values,
                'last_modified_at' => now(),
            ])->save();

            return true;
        });
    }

    public function deleteSubscription($subscriptionId): void
    {
        Dav::model(DavCalendarSubscription::class)::query()
            ->whereKey($subscriptionId)
            ->delete();
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
        return $this->calendar($calendarId)->putObject((string) $calendarData, (string) $objectUri)->quotedEtag();
    }

    public function updateCalendarObject($calendarId, $objectUri, $calendarData): string
    {
        return $this->calendar($calendarId)->putObject((string) $calendarData, (string) $objectUri)->quotedEtag();
    }

    public function deleteCalendarObject($calendarId, $objectUri): void
    {
        $this->calendar($calendarId)->objects()->where('uri', $objectUri)->first()?->deleteDavResource();
    }

    /**
     * @return array{syncToken: string, added: array<int, string>, modified: array<int, string>, deleted: array<int, string>}|null
     */
    public function getChangesForCalendar($calendarId, $syncToken, $syncLevel, $limit = null): ?array
    {
        $calendar = Dav::model(DavCalendar::class)::query()->find($this->calendarKey($calendarId));
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
    private function calendarRow(DavCalendarInstance $instance): array
    {
        $calendar = $instance->calendar;
        $components = $calendar->components ?: ['VEVENT', 'VTODO', 'VJOURNAL'];

        return [
            'id' => [(int) $calendar->id, (int) $instance->id],
            'uri' => $instance->uri,
            'principaluri' => $this->principalUri($instance->owner_id),
            self::DisplayNameProperty => $instance->display_name,
            self::DescriptionProperty => $instance->description,
            self::ColorProperty => $instance->color,
            self::TimezoneProperty => $this->calendarTimezoneProperty($instance->timezone),
            self::SupportedComponentsProperty => new SupportedCalendarComponentSet($components),
            self::SyncTokenProperty => $this->davSyncToken($calendar->sync_token),
            'share-access' => $instance->access,
            'share-resource-uri' => '/ns/share/'.$calendar->id,
            'read-only' => $instance->access === SharingPlugin::ACCESS_READ,
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

        Dav::model(DavSchedulingObject::class)::query()->updateOrCreate(
            ['owner_id' => $userId, 'uri' => (string) $objectUri],
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
        $model = Dav::model(DavSchedulingObject::class);

        return $model::query()->where('owner_id', $userId);
    }

    /**
     * @return Builder<DavCalendarSubscription>
     */
    private function subscriptionQuery(int|string $userId): Builder
    {
        /** @var class-string<DavCalendarSubscription> $model */
        $model = Dav::model(DavCalendarSubscription::class);

        return $model::query()->where('owner_id', $userId);
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionRow(DavCalendarSubscription $subscription): array
    {
        $row = [
            'id' => (int) $subscription->id,
            'uri' => $subscription->uri,
            'principaluri' => $this->principalUri($subscription->owner_id),
            'source' => $subscription->source,
            'lastmodified' => $subscription->last_modified_at?->getTimestamp(),
            self::DisplayNameProperty => $subscription->display_name,
            self::SubscriptionOrderProperty => $subscription->order,
            self::SupportedComponentsProperty => new SupportedCalendarComponentSet(['VTODO', 'VEVENT']),
        ];

        if ($subscription->color !== null) {
            $row[self::ColorProperty] = $subscription->color;
        }

        if ($subscription->refresh_rate !== null) {
            $row[self::SubscriptionRefreshRateProperty] = $subscription->refresh_rate;
        }

        if ($subscription->strip_todos) {
            $row[self::SubscriptionStripTodosProperty] = true;
        }

        if ($subscription->strip_alarms) {
            $row[self::SubscriptionStripAlarmsProperty] = true;
        }

        if ($subscription->strip_attachments) {
            $row[self::SubscriptionStripAttachmentsProperty] = true;
        }

        return $row;
    }

    private function subscriptionHref(mixed $property): ?string
    {
        if ($property instanceof Href) {
            return $property->getHref();
        }

        return is_string($property) && $property !== '' ? $property : null;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function subscriptionFlag(array $properties, string $property): bool
    {
        return array_key_exists($property, $properties);
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

    private function calendar(mixed $calendarId): DavCalendar
    {
        return Dav::model(DavCalendar::class)::query()->findOrFail($this->calendarKey($calendarId));
    }

    private function calendarInstance(mixed $calendarId): ?DavCalendarInstance
    {
        $instanceId = is_array($calendarId) ? ($calendarId[1] ?? null) : null;

        if ($instanceId === null) {
            return null;
        }

        return Dav::model(DavCalendarInstance::class)::query()->find($instanceId);
    }

    private function calendarKey(mixed $calendarId): int|string
    {
        return is_array($calendarId) ? $calendarId[0] : $calendarId;
    }

    private function ownerExists(int $userId): bool
    {
        $model = Dav::ownerModel();

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
