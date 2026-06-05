<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Casts\CalendarObjectDataCast;
use Bambamboole\LaravelDav\Contracts\DavOwner;
use Bambamboole\LaravelDav\Database\Factories\DavCalendarObjectFactory;
use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\Concerns\QueriesDavResources;
use Bambamboole\LaravelDav\Models\Concerns\TracksDavResource;
use Bambamboole\LaravelDav\Parsing\CalendarObjectSerializer;
use Bambamboole\LaravelDav\Support\DtoFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForOwner(Builder $query, DavOwner|int|string $owner): Builder
    {
        return $query->whereHas('calendar', fn (Builder $query): Builder => $query->where('user_id', self::resolveOwnerId($owner)));
    }

    public function toData(): CalendarObjectData
    {
        return $this->data;
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
            'timezone' => $this->data->timezone ?: $this->calendar->timezone,
        ]);

        $serializer = app(CalendarObjectSerializer::class);
        $original = $this->getRawOriginal('calendar_data');

        return $this->exists && filled($original)
            ? $serializer->merge((string) $original, $data)
            : $serializer->serialize($data);
    }

    protected static function newFactory(): DavCalendarObjectFactory
    {
        return DavCalendarObjectFactory::new();
    }
}
