<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Contracts\DavOwner;
use Bambamboole\LaravelDav\Database\Factories\DavCalendarProxyMembershipFactory;
use Bambamboole\LaravelDav\Facades\Dav;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $owner_id
 * @property int $delegate_owner_id
 * @property string $access
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class DavCalendarProxyMembership extends Model
{
    /** @use HasFactory<DavCalendarProxyMembershipFactory> */
    use HasFactory;

    public const AccessRead = 'read';

    public const AccessWrite = 'write';

    protected $table = 'dav_calendar_proxy_memberships';

    protected $fillable = [
        'owner_id',
        'delegate_owner_id',
        'access',
    ];

    protected $attributes = [
        'access' => self::AccessRead,
    ];

    public static function grant(DavOwner|int|string $owner, DavOwner|int|string $delegate, string $access = self::AccessRead): static
    {
        return DB::transaction(fn (): static => static::query()->updateOrCreate(
            [
                'owner_id' => self::resolveOwnerId($owner),
                'delegate_owner_id' => self::resolveOwnerId($delegate),
            ],
            ['access' => $access],
        ));
    }

    public static function revoke(DavOwner|int|string $owner, DavOwner|int|string $delegate, ?string $access = null): void
    {
        DB::transaction(function () use ($owner, $delegate, $access): void {
            $query = static::query()
                ->where('owner_id', self::resolveOwnerId($owner))
                ->where('delegate_owner_id', self::resolveOwnerId($delegate));

            if ($access !== null) {
                $query->where('access', $access);
            }

            $query->delete();
        });
    }

    /**
     * @param  iterable<DavOwner|int|string>  $delegates
     */
    public static function setDelegates(DavOwner|int|string $owner, string $access, iterable $delegates): void
    {
        DB::transaction(function () use ($owner, $access, $delegates): void {
            $ownerId = self::resolveOwnerId($owner);
            $delegateOwnerIds = collect($delegates)
                ->map(fn (DavOwner|int|string $delegate): int|string => self::resolveOwnerId($delegate))
                ->unique()
                ->values();

            static::query()
                ->where('owner_id', $ownerId)
                ->where('access', $access)
                ->whereNotIn('delegate_owner_id', $delegateOwnerIds->all())
                ->delete();

            foreach ($delegateOwnerIds as $delegateOwnerId) {
                static::query()->updateOrCreate(
                    [
                        'owner_id' => $ownerId,
                        'delegate_owner_id' => $delegateOwnerId,
                    ],
                    ['access' => $access],
                );
            }
        });
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function owner(): BelongsTo
    {
        $ownerModel = Dav::ownerModel();

        return $this->belongsTo($ownerModel, 'owner_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function delegateOwner(): BelongsTo
    {
        $ownerModel = Dav::ownerModel();

        return $this->belongsTo($ownerModel, 'delegate_owner_id');
    }

    private static function resolveOwnerId(DavOwner|int|string $owner): int|string
    {
        return $owner instanceof DavOwner ? $owner->getDavPrincipalId() : $owner;
    }

    protected static function newFactory(): DavCalendarProxyMembershipFactory
    {
        return DavCalendarProxyMembershipFactory::new();
    }
}
