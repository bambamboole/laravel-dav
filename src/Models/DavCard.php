<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Casts\ContactDataCast;
use Bambamboole\LaravelDav\Contracts\DavOwner;
use Bambamboole\LaravelDav\Database\Factories\DavCardFactory;
use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\Concerns\QueriesDavResources;
use Bambamboole\LaravelDav\Models\Concerns\TracksDavResource;
use Bambamboole\LaravelDav\Parsing\VCardParser;
use Bambamboole\LaravelDav\Parsing\VCardSerializer;
use Bambamboole\LaravelDav\Support\DtoFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    use QueriesDavResources;
    use TracksDavResource;

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
        return $this->belongsTo(Dav::model(DavAddressBook::class), 'dav_address_book_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function forOwner(Builder $query, DavOwner|int|string $owner): Builder
    {
        return $query->whereHas('addressBook', fn (Builder $query): Builder => $query->where('owner_id', self::resolveOwnerId($owner)));
    }

    public function toData(): ContactData
    {
        return $this->data;
    }

    public function fillFromDavData(ContactData|string $data, ?string $uri = null): static
    {
        if ($uri !== null) {
            $this->uri = $uri;
        }

        if (is_string($data)) {
            $payloadUri = $uri ?? $this->uri;

            $this->fill([
                'data' => app(VCardParser::class)->parse($data, $payloadUri),
                'card_data' => $data,
            ]);

            return $this;
        }

        $this->fill(['data' => $uri === null ? $data : DtoFactory::contactData($data, ['uri' => $uri])]);

        return $this;
    }

    public function replaceWith(ContactData|string $data, ?string $expectedEtag = null): static
    {
        return DB::transaction(function () use ($data, $expectedEtag): static {
            if ($expectedEtag !== null) {
                $this->expectingEtag($expectedEtag);
            }

            $this->fillFromDavData($data, $this->uri)->save();

            return $this;
        });
    }

    public function deleteDavResource(?string $expectedEtag = null): bool
    {
        return DB::transaction(function () use ($expectedEtag): bool {
            if ($expectedEtag !== null) {
                $this->expectingEtag($expectedEtag);
            }

            return (bool) $this->delete();
        });
    }

    public function quotedEtag(): string
    {
        return '"'.$this->etag.'"';
    }

    protected function payloadColumn(): string
    {
        return 'card_data';
    }

    protected function changeCollection(): DavAddressBook
    {
        return $this->addressBook;
    }

    protected function applyDavDefaults(): void
    {
        $uid = $this->data->uid ?: $this->originalUid() ?: (string) Str::uuid();

        if ($this->data->uid !== $uid) {
            $this->data = DtoFactory::contactData($this->data, ['uid' => $uid]);
        }

        if (blank($this->uri)) {
            $this->uri = $uid.'.vcf';
        }
    }

    protected function buildPayload(): string
    {
        $serializer = app(VCardSerializer::class);
        $original = $this->getRawOriginal('card_data');

        return $this->exists && filled($original)
            ? $serializer->merge((string) $original, $this->data)
            : $serializer->serialize($this->data);
    }

    private function originalUid(): ?string
    {
        $original = $this->getRawOriginal('data');

        if (! is_string($original) || $original === '') {
            return null;
        }

        $decoded = json_decode($original, true);
        $uid = is_array($decoded) ? ($decoded['uid'] ?? null) : null;

        return is_string($uid) ? $uid : null;
    }

    protected static function newFactory(): DavCardFactory
    {
        return DavCardFactory::new();
    }
}
