<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Exceptions\StaleDavResourceException;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCard;
use Bambamboole\LaravelDav\Parsing\VCardSerializer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContactCardWriter
{
    public function __construct(
        private VCardSerializer $serializer,
        private DavChangeRecorder $changeRecorder,
    ) {}

    public function create(DavAddressBook $addressBook, ContactData $data): DavCard
    {
        return DB::transaction(function () use ($addressBook, $data): DavCard {
            $uid = $data->uid ?: (string) Str::uuid();
            $uri = $data->uri !== '' ? $data->uri : $uid.'.vcf';
            $data = DtoFactory::contactData($data, ['uri' => $uri, 'uid' => $uid]);
            $payload = $this->serializer->serialize($data);

            $card = DavCard::createFromData($addressBook, $uri, $data, $payload);

            $this->changeRecorder->recordAddressBookChange($addressBook, $card->uri, DavChangeOperation::Add);

            return $card->refresh();
        });
    }

    public function update(DavCard $card, ContactData $data, string $expectedEtag): DavCard
    {
        return DB::transaction(function () use ($card, $data, $expectedEtag): DavCard {
            $fresh = $card->newQuery()->whereKey($card->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->etag !== $expectedEtag) {
                throw new StaleDavResourceException($expectedEtag, $fresh->etag, $fresh->uri);
            }

            $data = DtoFactory::contactData($data, [
                'uri' => $fresh->uri,
                'uid' => $data->uid ?: $fresh->toData()->uid ?: (string) Str::uuid(),
            ]);
            $payload = $this->serializer->merge($fresh->card_data, $data);

            $fresh->updateFromData($data, $payload);

            $addressBook = $fresh->addressBook()->firstOrFail();
            $this->changeRecorder->recordAddressBookChange($addressBook, $fresh->uri, DavChangeOperation::Modify);

            return $fresh->refresh();
        });
    }

    public function delete(DavCard $card, string $expectedEtag): void
    {
        DB::transaction(function () use ($card, $expectedEtag): void {
            $fresh = $card->newQuery()->whereKey($card->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->etag !== $expectedEtag) {
                throw new StaleDavResourceException($expectedEtag, $fresh->etag, $fresh->uri);
            }

            $addressBook = $fresh->addressBook()->firstOrFail();
            $uri = $fresh->uri;

            $fresh->delete();

            $this->changeRecorder->recordAddressBookChange($addressBook, $uri, DavChangeOperation::Delete);
        });
    }
}
