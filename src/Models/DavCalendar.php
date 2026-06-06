<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Contracts\DavOwner;
use Bambamboole\LaravelDav\Database\Factories\DavCalendarFactory;
use Bambamboole\LaravelDav\Dto\CalendarObjectData;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Sabre\DAV\Sharing\Plugin as SharingPlugin;

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
        return $this->hasMany(Dav::model(DavCalendarInstance::class), 'dav_calendar_id');
    }

    /**
     * @return HasOne<DavCalendarInstance, $this>
     */
    public function ownerInstance(): HasOne
    {
        return $this->hasOne(Dav::model(DavCalendarInstance::class), 'dav_calendar_id')
            ->where('access', DavCalendarInstance::AccessOwner);
    }

    /**
     * @return HasMany<DavCalendarObject, $this>
     */
    public function objects(): HasMany
    {
        return $this->hasMany(Dav::model(DavCalendarObject::class), 'dav_calendar_id');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function createForOwner(DavOwner|int|string $owner, array $attributes = []): static
    {
        return DB::transaction(function () use ($owner, $attributes): static {
            $ownerId = self::resolveOwnerId($owner);
            $uri = (string) ($attributes['uri'] ?? 'default');

            $calendar = static::query()->create([
                'owner_id' => $ownerId,
                'components' => $attributes['components'] ?? ['VEVENT', 'VTODO', 'VJOURNAL'],
                'sync_token' => $attributes['sync_token'] ?? 1,
            ]);

            $calendar->instances()->create([
                'owner_id' => $ownerId,
                'uri' => $uri,
                'access' => DavCalendarInstance::AccessOwner,
                'display_name' => (string) ($attributes['display_name'] ?? $uri),
                'description' => $attributes['description'] ?? null,
                'color' => $attributes['color'] ?? null,
                'timezone' => $attributes['timezone'] ?? null,
                'order' => (int) ($attributes['order'] ?? 0),
                'transparent' => (bool) ($attributes['transparent'] ?? false),
            ]);

            return $calendar->load('ownerInstance');
        });
    }

    public function putObject(CalendarObjectData|string $data, ?string $uri = null, ?string $expectedEtag = null): DavCalendarObject
    {
        return DB::transaction(function () use ($data, $uri, $expectedEtag): DavCalendarObject {
            $object = $uri === null
                ? $this->objects()->make()
                : $this->objects()->firstOrNew(['uri' => $uri]);

            if ($expectedEtag !== null) {
                $object->expectingEtag($expectedEtag);
            }

            $object->fillFromDavData($data, $uri)->save();

            return $object;
        });
    }

    public function shareWith(
        DavOwner|int|string $owner,
        int $access = DavCalendarInstance::AccessRead,
        ?string $shareHref = null,
        ?string $shareDisplayName = null,
    ): DavCalendarInstance {
        return DB::transaction(function () use ($owner, $access, $shareHref, $shareDisplayName): DavCalendarInstance {
            $ownerId = self::resolveOwnerId($owner);
            $sourceInstance = $this->ownerInstance()->firstOrFail();
            $instance = $this->instances()
                ->where(function (Builder $query) use ($ownerId, $shareHref): void {
                    $query->where('owner_id', $ownerId);

                    if ($shareHref !== null) {
                        $query->orWhere('share_href', $shareHref);
                    }
                })
                ->first() ?? $this->instances()->make(['owner_id' => $ownerId]);

            $instance->forceFill([
                'owner_id' => $ownerId,
                'uri' => $instance->exists ? $instance->uri : (string) Str::uuid(),
                'access' => $access,
                'display_name' => $sourceInstance->display_name,
                'description' => $sourceInstance->description,
                'color' => $sourceInstance->color,
                'timezone' => $sourceInstance->timezone,
                'order' => $sourceInstance->order,
                'transparent' => true,
                'share_href' => $shareHref,
                'share_display_name' => $shareDisplayName,
                'share_invite_status' => SharingPlugin::INVITE_ACCEPTED,
            ])->save();

            return $instance;
        });
    }

    public function unshareWith(DavOwner|int|string $owner): void
    {
        $ownerId = self::resolveOwnerId($owner);

        $this->instances()
            ->where('owner_id', $ownerId)
            ->whereIn('access', [DavCalendarInstance::AccessRead, DavCalendarInstance::AccessReadWrite])
            ->delete();
    }

    public function unshareByHref(string $shareHref): void
    {
        $this->instances()
            ->where('share_href', $shareHref)
            ->whereIn('access', [DavCalendarInstance::AccessRead, DavCalendarInstance::AccessReadWrite])
            ->delete();
    }

    protected static function newFactory(): DavCalendarFactory
    {
        return DavCalendarFactory::new();
    }
}
