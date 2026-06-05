<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Database\Factories\DavCredentialFactory;
use Bambamboole\LaravelDav\Facades\Dav;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property string $username
 * @property string $secret_hash
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class DavCredential extends Model
{
    /** @use HasFactory<DavCredentialFactory> */
    use HasFactory;

    protected $table = 'dav_credentials';

    protected $fillable = [
        'owner_id',
        'name',
        'username',
        'secret_hash',
        'last_used_at',
    ];

    protected $hidden = [
        'secret_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Dav::ownerModel(), 'owner_id');
    }

    protected static function newFactory(): DavCredentialFactory
    {
        return DavCredentialFactory::new();
    }
}
