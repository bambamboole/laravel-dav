<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Database\Factories\DavCalendarProxyMembershipFactory;
use Bambamboole\LaravelDav\Facades\Dav;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected static function newFactory(): DavCalendarProxyMembershipFactory
    {
        return DavCalendarProxyMembershipFactory::new();
    }
}
