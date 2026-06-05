<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Casts\ContactDataCast;
use Bambamboole\LaravelDav\Database\Factories\DavCardFactory;
use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Parsing\VCardSerializer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dav_address_book_id
 * @property string $uri
 * @property ContactData $data
 * @property string $etag
 * @property int $size
 * @property CarbonImmutable $last_modified_at
 * @property string $card_data
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read DavAddressBook $addressBook
 */
class DavCard extends Model
{
    /** @use HasFactory<DavCardFactory> */
    use HasFactory;

    protected $table = 'dav_cards';

    protected $fillable = [
        'dav_address_book_id',
        'uri',
        'data',
        'etag',
        'size',
        'last_modified_at',
        'card_data',
    ];

    protected $attributes = [
        'data' => '[]',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => ContactDataCast::class,
            'size' => 'integer',
            'last_modified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DavAddressBook, $this>
     */
    public function addressBook(): BelongsTo
    {
        return $this->belongsTo(Dav::modelFor('address_book', DavAddressBook::class), 'dav_address_book_id');
    }

    public function toData(): ContactData
    {
        return $this->data;
    }

    public static function createFromData(DavAddressBook $addressBook, string $uri, ContactData $data, ?string $payload = null): self
    {
        return $addressBook->cards()->create([
            ...self::attributesFromData($data),
            'uri' => $uri,
            'card_data' => self::payloadFromData($data, $payload),
            'last_modified_at' => now(),
        ]);
    }

    public function updateFromData(ContactData $data, ?string $payload = null): bool
    {
        return $this->forceFill([
            ...self::attributesFromData($data),
            'card_data' => self::payloadFromData($data, $payload),
            'last_modified_at' => now(),
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private static function attributesFromData(ContactData $data): array
    {
        return [
            'data' => $data,
        ];
    }

    private static function payloadFromData(ContactData $data, ?string $payload): ?string
    {
        $payload ??= $data->raw;

        return blank($payload) ? null : $payload;
    }

    /**
     * Keep the payload, etag, and size consistent on every save. The vCard
     * payload is derived from the structured attributes only when none was
     * supplied (a DAV client's raw payload is authoritative); the etag and size
     * are always a pure function of that payload.
     */
    protected static function booted(): void
    {
        static::saving(function (self $card): void {
            if (blank($card->card_data)) {
                $card->card_data = app(VCardSerializer::class)->serialize($card->toData());
            }

            $card->etag = sha1($card->card_data);
            $card->size = strlen($card->card_data);
        });
    }

    protected static function newFactory(): DavCardFactory
    {
        return DavCardFactory::new();
    }
}
