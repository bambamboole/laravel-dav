<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Contracts\DavOwner;
use Bambamboole\LaravelDav\Database\Factories\DavCalendarFactory;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\Concerns\QueriesDavResources;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $owner_id
 * @property array<array-key, mixed> $components
 * @property int $sync_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, DavCalendarInstance> $instances
 * @property-read int|null $instances_count
 * @property-read DavCalendarInstance|null $ownerInstance
 * @property-read Collection<int, DavCalendarObject> $objects
 * @property-read int|null $objects_count
 */
class DavCalendar extends Model
{
    /** @use HasFactory<DavCalendarFactory> */
    use HasFactory;

    use QueriesDavResources;

    protected $table = 'dav_calendars';

    protected $fillable = [
        'owner_id',
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
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Dav::ownerModel(), 'owner_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function forOwner(Builder $query, DavOwner|int|string $owner): Builder
    {
        return $query->where('owner_id', self::resolveOwnerId($owner));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function forKey(Builder $query, int|string $id): Builder
    {
        return $query->where(function (Builder $query) use ($id): void {
            if (is_numeric($id)) {
                $query->whereKey($id);
            }

            $query->orWhereHas('instances', fn (Builder $query): Builder => $query->where('uri', $id));
        });
    }

    /**
     * @return HasMany<DavCalendarInstance, $this>
     */
    public function instances(): HasMany
    {
        return $this->hasMany(Dav::modelFor('calendar_instance', DavCalendarInstance::class), 'dav_calendar_id');
    }

    /**
     * @return HasOne<DavCalendarInstance, $this>
     */
    public function ownerInstance(): HasOne
    {
        return $this->hasOne(Dav::modelFor('calendar_instance', DavCalendarInstance::class), 'dav_calendar_id')
            ->where('access', DavCalendarInstance::AccessOwner);
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
