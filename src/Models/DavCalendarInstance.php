<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Contracts\DavOwner;
use Bambamboole\LaravelDav\Database\Factories\DavCalendarInstanceFactory;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\Concerns\QueriesDavResources;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Sabre\DAV\Sharing\Plugin as SharingPlugin;

/**
 * @property int $id
 * @property int $dav_calendar_id
 * @property int $owner_id
 * @property string $uri
 * @property int $access
 * @property string $display_name
 * @property string|null $description
 * @property string|null $color
 * @property string|null $timezone
 * @property int $order
 * @property bool $transparent
 * @property string|null $share_href
 * @property string|null $share_display_name
 * @property int|null $share_invite_status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read DavCalendar $calendar
 */
class DavCalendarInstance extends Model
{
    /** @use HasFactory<DavCalendarInstanceFactory> */
    use HasFactory;

    use QueriesDavResources;

    public const AccessOwner = SharingPlugin::ACCESS_SHAREDOWNER;

    public const AccessRead = SharingPlugin::ACCESS_READ;

    public const AccessReadWrite = SharingPlugin::ACCESS_READWRITE;

    protected $table = 'dav_calendar_instances';

    protected $fillable = [
        'dav_calendar_id',
        'owner_id',
        'uri',
        'access',
        'display_name',
        'description',
        'color',
        'timezone',
        'order',
        'transparent',
        'share_href',
        'share_display_name',
        'share_invite_status',
    ];

    protected $attributes = [
        'access' => self::AccessOwner,
        'order' => 0,
        'transparent' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access' => 'integer',
            'order' => 'integer',
            'transparent' => 'boolean',
            'share_invite_status' => 'integer',
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
     * @return BelongsTo<Model, $this>
     */
    public function owner(): BelongsTo
    {
        $ownerModel = Dav::ownerModel();

        return $this->belongsTo($ownerModel, 'owner_id');
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

    protected static function newFactory(): DavCalendarInstanceFactory
    {
        return DavCalendarInstanceFactory::new();
    }
}
