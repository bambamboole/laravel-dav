<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Database\Factories\DavChangeFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $collection_type
 * @property int $collection_id
 * @property string|null $resource_uri
 * @property int $operation
 * @property int $sync_token
 * @property CarbonImmutable $created_at
 */
#[WithoutTimestamps]
class DavChange extends Model
{
    /** @use HasFactory<DavChangeFactory> */
    use HasFactory;

    protected $fillable = [
        'collection_type',
        'collection_id',
        'resource_uri',
        'operation',
        'sync_token',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'collection_id' => 'integer',
            'operation' => 'integer',
            'sync_token' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function newFactory()
    {
        return DavChangeFactory::new();
    }
}
