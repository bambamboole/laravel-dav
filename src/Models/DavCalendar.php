<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Database\Factories\DavCalendarFactory;
use Bambamboole\LaravelDav\Facades\Dav;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $uri
 * @property string $display_name
 * @property string|null $description
 * @property string|null $color
 * @property string|null $timezone
 * @property array<array-key, mixed> $components
 * @property int $sync_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, DavCalendarObject> $objects
 * @property-read int|null $objects_count
 */
class DavCalendar extends Model
{
    /** @use HasFactory<DavCalendarFactory> */
    use HasFactory;

    protected $table = 'dav_calendars';

    protected $fillable = [
        'user_id',
        'uri',
        'display_name',
        'description',
        'color',
        'timezone',
        'components',
        'sync_token',
    ];

    protected $attributes = [
        'components' => '["VEVENT","VTODO","VJOURNAL"]',
        'sync_token' => 1,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'components' => 'array',
            'sync_token' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $ownerModel */
        $ownerModel = config('dav.owner_model');

        return $this->belongsTo($ownerModel);
    }

    /**
     * @return HasMany<DavCalendarObject, $this>
     */
    public function objects(): HasMany
    {
        return $this->hasMany(Dav::modelFor('calendar_object', DavCalendarObject::class), 'dav_calendar_id');
    }

    protected static function newFactory(): DavCalendarFactory
    {
        return DavCalendarFactory::new();
    }
}
