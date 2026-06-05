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
            $data = $this->withIdentity($data, $uri, $uid);
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

            $data = $this->withIdentity($data, $fresh->uri, $data->uid ?: $fresh->uid ?: (string) Str::uuid());
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

    private function withIdentity(ContactData $data, string $uri, string $uid): ContactData
    {
        return new ContactData(
            uri: $uri,
            raw: $data->raw,
            etag: $data->etag,
            size: $data->size,
            uid: $uid,
            formattedName: $data->formattedName,
            givenName: $data->givenName,
            familyName: $data->familyName,
            organization: $data->organization,
            contactType: $data->contactType,
            birthday: $data->birthday,
            emailAddresses: $data->emailAddresses,
            phoneNumbers: $data->phoneNumbers,
            addresses: $data->addresses,
            urls: $data->urls,
            instantMessages: $data->instantMessages,
            socialProfiles: $data->socialProfiles,
            dates: $data->dates,
            relations: $data->relations,
            extensions: $data->extensions,
            pronouns: $data->pronouns,
            namePrefix: $data->namePrefix,
            middleName: $data->middleName,
            phoneticGivenName: $data->phoneticGivenName,
            phoneticMiddleName: $data->phoneticMiddleName,
            phoneticFamilyName: $data->phoneticFamilyName,
            phoneticOrganization: $data->phoneticOrganization,
            previousFamilyName: $data->previousFamilyName,
            nameSuffix: $data->nameSuffix,
            nickname: $data->nickname,
            jobTitle: $data->jobTitle,
            department: $data->department,
            note: $data->note,
        );
    }
}
