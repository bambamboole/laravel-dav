<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Database\Factories\DavLockFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $owner
 * @property int $timeout
 * @property int $created
 * @property string $token
 * @property int $scope
 * @property int $depth
 * @property string $uri
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'owner',
    'timeout',
    'created',
    'token',
    'scope',
    'depth',
    'uri',
])]
class DavLock extends Model
{
    /** @use HasFactory<DavLockFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'timeout' => 'integer',
            'created' => 'integer',
            'scope' => 'integer',
            'depth' => 'integer',
        ];
    }

    protected static function newFactory()
    {
        return DavLockFactory::new();
    }
}
