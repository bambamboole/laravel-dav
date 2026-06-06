<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Contracts\DavOwner;
use Bambamboole\LaravelDav\Database\Factories\DavAddressBookFactory;
use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\Concerns\QueriesDavResources;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $owner_id
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

    use QueriesDavResources;

    protected $table = 'dav_address_books';

    protected $fillable = [
        'owner_id',
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

    /**
     * @return HasMany<DavCard, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Dav::model(DavCard::class), 'dav_address_book_id');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function createForOwner(DavOwner|int|string $owner, array $attributes = []): static
    {
        $ownerId = self::resolveOwnerId($owner);
        $uri = (string) ($attributes['uri'] ?? 'default');

        return static::query()->create([
            'owner_id' => $ownerId,
            'uri' => $uri,
            'display_name' => (string) ($attributes['display_name'] ?? $uri),
            'description' => $attributes['description'] ?? null,
            'sync_token' => $attributes['sync_token'] ?? 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateDavProperties(array $attributes): static
    {
        $values = array_intersect_key($attributes, array_flip([
            'uri',
            'display_name',
            'description',
        ]));

        if ($values !== []) {
            $this->forceFill($values)->save();
        }

        return $this;
    }

    public function putContact(ContactData|string $data, ?string $uri = null, ?string $expectedEtag = null): DavCard
    {
        return DB::transaction(function () use ($data, $uri, $expectedEtag): DavCard {
            $card = $uri === null
                ? $this->cards()->make()
                : $this->cards()->firstOrNew(['uri' => $uri]);

            if ($expectedEtag !== null) {
                $card->expectingEtag($expectedEtag);
            }

            $card->fillFromDavData($data, $uri)->save();

            return $card;
        });
    }

    public function deleteDavAddressBook(): void
    {
        $this->delete();
    }

    protected static function newFactory(): DavAddressBookFactory
    {
        return DavAddressBookFactory::new();
    }
}
