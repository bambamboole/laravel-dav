<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Casts\CalendarObjectDataCast;
use Bambamboole\LaravelDav\Contracts\DavOwner;
use Bambamboole\LaravelDav\Database\Factories\DavCalendarObjectFactory;
use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\Concerns\QueriesDavResources;
use Bambamboole\LaravelDav\Models\Concerns\TracksDavResource;
use Bambamboole\LaravelDav\Parsing\CalendarObjectParser;
use Bambamboole\LaravelDav\Parsing\CalendarObjectSerializer;
use Bambamboole\LaravelDav\Support\DtoFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Sabre\VObject;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

/**
 * @property int $id
 * @property int $dav_calendar_id
 * @property string $uri
 * @property string|null $uid
 * @property string|null $component_type
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property bool $is_all_day
 * @property bool $recurs
 * @property string|null $timezone
 * @property CalendarObjectData $data
 * @property string $etag
 * @property string|null $schedule_tag
 * @property int $size
 * @property CarbonImmutable $last_modified_at
 * @property string $calendar_data
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read DavCalendar $calendar
 */
class DavCalendarObject extends Model
{
    /** @use HasFactory<DavCalendarObjectFactory> */
    use HasFactory;

    use QueriesDavResources;
    use TracksDavResource;

    protected $table = 'dav_calendar_objects';

    protected $fillable = [
        'dav_calendar_id',
        'uri',
        'uid',
        'component_type',
        'starts_at',
        'ends_at',
        'is_all_day',
        'recurs',
        'timezone',
        'data',
        'etag',
        'schedule_tag',
        'size',
        'last_modified_at',
        'calendar_data',
    ];

    protected $attributes = [
        'data' => '[]',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_all_day' => 'boolean',
            'recurs' => 'boolean',
            'data' => CalendarObjectDataCast::class,
            'size' => 'integer',
            'last_modified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DavCalendar, $this>
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Dav::model(DavCalendar::class), 'dav_calendar_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function forOwner(Builder $query, DavOwner|int|string $owner): Builder
    {
        return $query->whereHas('calendar.instances', fn (Builder $query): Builder => $query->where('owner_id', self::resolveOwnerId($owner)));
    }

    public function toData(): CalendarObjectData
    {
        return $this->data;
    }

    public function fillFromDavData(CalendarObjectData|string $data, ?string $uri = null): static
    {
        if ($uri !== null) {
            $this->uri = $uri;
        }

        if (is_string($data)) {
            $currentPayload = $this->exists ? $this->calendar_data : null;
            $payloadUri = $uri ?? $this->uri;

            $this->fill([
                'data' => app(CalendarObjectParser::class)->parse($data, $payloadUri),
                'calendar_data' => $data,
            ]);

            $this->refreshScheduleTag($currentPayload, $data);

            return $this;
        }

        $this->fill(['data' => $uri === null ? $data : DtoFactory::calendarObjectData($data, ['uri' => $uri])]);

        return $this;
    }

    public function replaceWith(CalendarObjectData|string $data, ?string $expectedEtag = null): static
    {
        return DB::transaction(function () use ($data, $expectedEtag): static {
            if ($expectedEtag !== null) {
                $this->expectingEtag($expectedEtag);
            }

            $this->fillFromDavData($data, $this->uri)->save();

            return $this;
        });
    }

    public function deleteDavResource(?string $expectedEtag = null): bool
    {
        return DB::transaction(function () use ($expectedEtag): bool {
            if ($expectedEtag !== null) {
                $this->expectingEtag($expectedEtag);
            }

            return (bool) $this->delete();
        });
    }

    public function quotedEtag(): string
    {
        return '"'.$this->etag.'"';
    }

    protected function payloadColumn(): string
    {
        return 'calendar_data';
    }

    protected function changeCollection(): DavCalendar
    {
        return $this->calendar;
    }

    protected function applyDavDefaults(): void
    {
        $uid = $this->data->uid ?: (string) Str::uuid();
        $componentType = $this->data->componentType ?: 'VEVENT';

        if ($this->data->uid !== $uid || $this->data->componentType !== $componentType) {
            $this->data = DtoFactory::calendarObjectData($this->data, [
                'uid' => $uid,
                'componentType' => $componentType,
            ]);
        }

        if (blank($this->uri)) {
            $this->uri = $uid.'.ics';
        }
    }

    protected function buildPayload(): string
    {
        $data = DtoFactory::calendarObjectData($this->data, [
            'uri' => $this->uri,
            'timezone' => $this->data->timezone ?: $this->calendar->ownerInstance?->timezone,
        ]);

        $serializer = app(CalendarObjectSerializer::class);
        $original = $this->getRawOriginal('calendar_data');

        return $this->exists && filled($original)
            ? $serializer->merge((string) $original, $data)
            : $serializer->serialize($data);
    }

    private function refreshScheduleTag(?string $currentPayload, string $payload): void
    {
        if (! $this->isSchedulingObject($payload)) {
            $this->schedule_tag = null;

            return;
        }

        if ($this->schedule_tag === null || ! $this->isAttendeeParticipantStatusOnlyChange($currentPayload, $payload)) {
            $this->schedule_tag = (string) Str::uuid();
        }
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

    private function isAttendeeParticipantStatusOnlyChange(?string $currentPayload, string $payload): bool
    {
        if ($currentPayload === null || $currentPayload === '') {
            return false;
        }

        if (! $this->isAttendeeSchedulingObject($payload)) {
            return false;
        }

        $current = $this->calendarWithoutParticipantStatus($currentPayload);
        $updated = $this->calendarWithoutParticipantStatus($payload);

        return $current !== null && $current === $updated;
    }

    private function isAttendeeSchedulingObject(string $payload): bool
    {
        $owner = $this->calendar->owner;

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

    protected static function newFactory(): DavCalendarObjectFactory
    {
        return DavCalendarObjectFactory::new();
    }
}
