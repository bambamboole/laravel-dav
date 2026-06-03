<?php

namespace Bambamboole\LaravelDav\Sabre\CalDav;

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Sabre\Concerns\RecordsDavChanges;
use Bambamboole\LaravelDav\Sabre\Concerns\ResolvesPrincipalUri;
use Illuminate\Support\Facades\DB;
use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Backend\SyncSupport;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\PropPatch;

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

        return DavCalendar::query()
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

        $calendar = DavCalendar::query()->create([
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
            $calendar = DavCalendar::query()->find($calendarId);

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
        DavCalendar::query()->whereKey($calendarId)->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCalendarObjects($calendarId): array
    {
        return DavCalendarObject::query()
            ->where('dav_calendar_id', $calendarId)
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
        $object = DavCalendarObject::query()
            ->where('dav_calendar_id', $calendarId)
            ->where('uri', $objectUri)
            ->first();

        return $object ? $this->objectRow($object, includeData: true) : null;
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
            $deleted = DavCalendarObject::query()
                ->where('dav_calendar_id', $calendarId)
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
        $calendar = DavCalendar::query()->find($calendarId);
        $syncToken = (string) $syncToken;

        if (! $calendar) {
            return null;
        }

        if ($syncToken === '') {
            return $this->currentResourceChangeResponse(
                $calendar->sync_token,
                DavCalendarObject::query()
                    ->where('dav_calendar_id', $calendar->id)
                    ->orderBy('id'),
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
        return DavCalendar::query()->findOrFail($calendarId);
    }

    private function ownerExists(int $userId): bool
    {
        $model = config('dav.owner_model');

        return $model::query()->whereKey($userId)->exists();
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
