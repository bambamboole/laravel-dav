<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Casts\CalendarObjectDataCast;
use Bambamboole\LaravelDav\Database\Factories\DavCalendarObjectFactory;
use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Parsing\CalendarObjectSerializer;
use Bambamboole\LaravelDav\Support\DtoFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dav_calendar_id
 * @property string $uri
 * @property string|null $uid
 * @property string|null $component_type
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property bool $is_all_day
 * @property string|null $timezone
 * @property CalendarObjectData $data
 * @property string $etag
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

    protected $table = 'dav_calendar_objects';

    protected $fillable = [
        'dav_calendar_id',
        'uri',
        'uid',
        'component_type',
        'starts_at',
        'ends_at',
        'is_all_day',
        'timezone',
        'data',
        'etag',
        'size',
        'last_modified_at',
        'calendar_data',
    ];

    protected $attributes = [
        'data' => '[]',
    ];

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
        return $this->belongsTo(Dav::modelFor('calendar', DavCalendar::class), 'dav_calendar_id');
    }

    public function toData(): CalendarObjectData
    {
        return $this->data;
    }

    public static function createFromData(
        DavCalendar $calendar,
        string $uri,
        CalendarObjectData $data,
        ?string $payload = null,
        ?string $defaultComponentType = 'VEVENT',
    ): self {
        return $calendar->objects()->create([
            ...self::attributesFromData($data, $defaultComponentType),
            'uri' => $uri,
            'calendar_data' => self::payloadFromData($data, $payload),
            'last_modified_at' => now(),
        ]);
    }

    public function updateFromData(
        CalendarObjectData $data,
        ?string $payload = null,
        ?string $defaultComponentType = 'VEVENT',
    ): bool {
        return $this->forceFill([
            ...self::attributesFromData($data, $defaultComponentType),
            'calendar_data' => self::payloadFromData($data, $payload),
            'last_modified_at' => now(),
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private static function attributesFromData(CalendarObjectData $data, ?string $defaultComponentType = 'VEVENT'): array
    {
        return [
            'data' => DtoFactory::calendarObjectData($data, [
                'componentType' => $data->componentType ?? $defaultComponentType,
            ]),
        ];
    }

    private static function payloadFromData(CalendarObjectData $data, ?string $payload): ?string
    {
        $payload ??= $data->raw;

        return blank($payload) ? null : $payload;
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

    protected static function newFactory(): DavCalendarObjectFactory
    {
        return DavCalendarObjectFactory::new();
    }
}
