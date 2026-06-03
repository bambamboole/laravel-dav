<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Database\Factories\DavCalendarObjectFactory;
use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Parsing\CalendarObjectSerializer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dav_calendar_id
 * @property string $uri
 * @property string|null $uid
 * @property string|null $component_type
 * @property string|null $summary
 * @property string|null $description
 * @property string|null $location
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property bool $is_all_day
 * @property string|null $timezone
 * @property string $etag
 * @property int $size
 * @property CarbonImmutable $last_modified_at
 * @property string $calendar_data
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property string|null $status
 * @property string|null $url
 * @property-read DavCalendar $calendar
 */
#[Fillable([
    'dav_calendar_id',
    'uri',
    'uid',
    'component_type',
    'summary',
    'description',
    'location',
    'status',
    'url',
    'starts_at',
    'ends_at',
    'is_all_day',
    'timezone',
    'etag',
    'size',
    'last_modified_at',
    'calendar_data',
])]
class DavCalendarObject extends Model
{
    /** @use HasFactory<DavCalendarObjectFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_all_day' => 'boolean',
            'size' => 'integer',
            'last_modified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DavCalendar, $this>
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(DavCalendar::class, 'dav_calendar_id');
    }

    public function toData(): CalendarObjectData
    {
        return new CalendarObjectData(
            uri: (string) $this->uri,
            raw: (string) ($this->calendar_data ?? ''),
            etag: (string) ($this->etag ?? ''),
            size: (int) ($this->size ?? 0),
            uid: $this->uid,
            componentType: $this->component_type,
            summary: $this->summary,
            description: $this->description,
            location: $this->location,
            status: $this->status,
            url: $this->url,
            startsAt: $this->starts_at ? CarbonImmutable::instance($this->starts_at) : null,
            endsAt: $this->ends_at ? CarbonImmutable::instance($this->ends_at) : null,
            isAllDay: (bool) $this->is_all_day,
            timezone: $this->timezone,
        );
    }

    /**
     * Keep the payload, etag, and size consistent on every save. The iCalendar
     * payload is derived from the structured attributes only when none was
     * supplied (a DAV client's raw payload is authoritative); the etag and size
     * are always a pure function of that payload.
     */
    protected static function booted(): void
    {
        static::saving(function (self $object): void {
            if (blank($object->calendar_data)) {
                $object->calendar_data = app(CalendarObjectSerializer::class)->serialize($object->toData());
            }

            $object->etag = sha1($object->calendar_data);
            $object->size = strlen($object->calendar_data);
        });
    }

    protected static function newFactory()
    {
        return DavCalendarObjectFactory::new();
    }
}
