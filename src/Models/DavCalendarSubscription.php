<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Database\Factories\DavCalendarSubscriptionFactory;
use Bambamboole\LaravelDav\Models\Concerns\BelongsToDavOwner;
use Bambamboole\LaravelDav\Models\Concerns\QueriesDavResources;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    use BelongsToDavOwner;

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

    protected static function newFactory(): DavCalendarSubscriptionFactory
    {
        return DavCalendarSubscriptionFactory::new();
    }
}
