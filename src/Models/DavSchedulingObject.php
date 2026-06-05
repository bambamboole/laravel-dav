<?php

namespace Bambamboole\LaravelDav\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * An iTip scheduling message delivered to a principal's scheduling inbox
 * (RFC 6638). The payload is stored verbatim; these objects are opaque and
 * transient, so they carry no typed DTO and are not part of the sync system.
 *
 * @property int $id
 * @property int $user_id
 * @property string $uri
 * @property string $calendar_data
 * @property string $etag
 * @property int $size
 * @property CarbonImmutable $last_modified_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class DavSchedulingObject extends Model
{
    protected $table = 'dav_scheduling_objects';

    protected $fillable = [
        'user_id',
        'uri',
        'calendar_data',
        'etag',
        'size',
        'last_modified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'last_modified_at' => 'datetime',
        ];
    }
}
