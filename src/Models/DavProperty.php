<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Database\Factories\DavPropertyFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $path
 * @property string $name
 * @property string $value
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class DavProperty extends Model
{
    /** @use HasFactory<DavPropertyFactory> */
    use HasFactory;

    protected $fillable = [
        'path',
        'name',
        'value',
    ];

    protected static function newFactory()
    {
        return DavPropertyFactory::new();
    }
}
