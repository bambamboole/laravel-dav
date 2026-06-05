<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Contracts\DavOwner;
use Bambamboole\LaravelDav\Database\Factories\DavCalendarSubscriptionFactory;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\Concerns\QueriesDavResources;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $uri
 * @property string $source
 * @property string $display_name
 * @property string|null $description
 * @property string|null $color
 * @property string|null $refresh_rate
 * @property int $order
 * @property bool $strip_todos
 * @property bool $strip_alarms
 * @property bool $strip_attachments
 * @property CarbonImmutable|null $last_modified_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class DavCalendarSubscription extends Model
{
    /** @use HasFactory<DavCalendarSubscriptionFactory> */
    use HasFactory;

    use QueriesDavResources;

    protected $table = 'dav_calendar_subscriptions';

    protected $fillable = [
        'owner_id',
        'uri',
        'source',
        'display_name',
        'description',
        'color',
        'refresh_rate',
        'order',
        'strip_todos',
        'strip_alarms',
        'strip_attachments',
        'last_modified_at',
    ];

    protected $attributes = [
        'order' => 0,
        'strip_todos' => false,
        'strip_alarms' => false,
        'strip_attachments' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
            'strip_todos' => 'boolean',
            'strip_alarms' => 'boolean',
            'strip_attachments' => 'boolean',
            'last_modified_at' => 'datetime',
        ];
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

    protected static function newFactory(): DavCalendarSubscriptionFactory
    {
        return DavCalendarSubscriptionFactory::new();
    }
}
