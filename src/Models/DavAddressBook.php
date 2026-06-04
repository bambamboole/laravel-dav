<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Database\Factories\DavAddressBookFactory;
use Bambamboole\LaravelDav\Facades\Dav;
use Carbon\CarbonImmutable;
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
class DavAddressBook extends Model
{
    /** @use HasFactory<DavAddressBookFactory> */
    use HasFactory;

    protected $table = 'dav_address_books';

    protected $fillable = [
        'user_id',
        'uri',
        'display_name',
        'description',
        'sync_token',
    ];

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
        /** @var class-string<Model> $ownerModel */
        $ownerModel = config('dav.owner_model');

        return $this->belongsTo($ownerModel);
    }

    /**
     * @return HasMany<DavCard, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Dav::modelFor('card', DavCard::class), 'dav_address_book_id');
    }

    protected static function newFactory(): DavAddressBookFactory
    {
        return DavAddressBookFactory::new();
    }
}
