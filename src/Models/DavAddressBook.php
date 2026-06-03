<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Database\Factories\DavAddressBookFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $uri
 * @property string $display_name
 * @property string|null $description
 * @property int $sync_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, DavCard> $cards
 * @property-read int|null $cards_count
 */
#[Fillable([
    'user_id',
    'uri',
    'display_name',
    'description',
    'sync_token',
])]
class DavAddressBook extends Model
{
    /** @use HasFactory<DavAddressBookFactory> */
    use HasFactory;

    protected $attributes = [
        'sync_token' => 1,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sync_token' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('dav.owner_model'));
    }

    /**
     * @return HasMany<DavCard, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(DavCard::class);
    }

    protected static function newFactory()
    {
        return DavAddressBookFactory::new();
    }
}
