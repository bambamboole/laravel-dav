<?php

namespace Bambamboole\LaravelDav\Dto;

use Bambamboole\LaravelDav\Dto\Contact\ContactDate;
use Bambamboole\LaravelDav\Dto\Contact\ContactEmailAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactInstantMessage;
use Bambamboole\LaravelDav\Dto\Contact\ContactPhoneNumber;
use Bambamboole\LaravelDav\Dto\Contact\ContactPostalAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactPronoun;
use Bambamboole\LaravelDav\Dto\Contact\ContactRelation;
use Bambamboole\LaravelDav\Dto\Contact\ContactSocialProfile;
use Bambamboole\LaravelDav\Dto\Contact\ContactUrl;
use Bambamboole\LaravelDav\Dto\Contact\ContactVCardExtension;

final readonly class ContactData
{
    /**
     * @param  array<int, ContactEmailAddress>  $emails
     * @param  array<int, ContactPhoneNumber>  $phones
     * @param  array<int, ContactPostalAddress>  $addresses
     * @param  array<int, ContactUrl>  $urls
     * @param  array<int, ContactInstantMessage>  $instantMessages
     * @param  array<int, ContactSocialProfile>  $socialProfiles
     * @param  array<int, ContactDate>  $dates
     * @param  array<int, ContactRelation>  $relations
     * @param  array<int, ContactVCardExtension>  $extensions
     * @param  array<int, ContactPronoun>  $pronouns
     * @param  array<int, string>  $simpleEmails  Plain email strings extracted from EMAIL properties, retained alongside the typed $emails so a card carrying only untyped emails round-trips losslessly.
     * @param  array<int, string>  $simplePhones  Plain phone strings extracted from TEL properties, retained alongside the typed $phones.
     */
    public function __construct(
        public string $uri,
        public string $raw,
        public string $etag,
        public int $size,
        public ?string $uid = null,
        public ?string $formattedName = null,
        public ?string $givenName = null,
        public ?string $familyName = null,
        public ?string $organization = null,
        public string $contactType = 'person',
        public ?ContactDate $birthday = null,
        public array $emails = [],
        public array $phones = [],
        public array $addresses = [],
        public array $urls = [],
        public array $instantMessages = [],
        public array $socialProfiles = [],
        public array $dates = [],
        public array $relations = [],
        public array $extensions = [],
        public array $pronouns = [],
        public ?string $namePrefix = null,
        public ?string $middleName = null,
        public ?string $nameSuffix = null,
        public ?string $nickname = null,
        public ?string $jobTitle = null,
        public ?string $department = null,
        public ?string $note = null,
        public array $simpleEmails = [],
        public array $simplePhones = [],
    ) {}

    public function withStorageMeta(string $uri, string $etag, int $size): self
    {
        return new self(
            uri: $uri,
            raw: $this->raw,
            etag: $etag,
            size: $size,
            uid: $this->uid,
            formattedName: $this->formattedName,
            givenName: $this->givenName,
            familyName: $this->familyName,
            organization: $this->organization,
            contactType: $this->contactType,
            birthday: $this->birthday,
            emails: $this->emails,
            phones: $this->phones,
            addresses: $this->addresses,
            urls: $this->urls,
            instantMessages: $this->instantMessages,
            socialProfiles: $this->socialProfiles,
            dates: $this->dates,
            relations: $this->relations,
            extensions: $this->extensions,
            pronouns: $this->pronouns,
            namePrefix: $this->namePrefix,
            middleName: $this->middleName,
            nameSuffix: $this->nameSuffix,
            nickname: $this->nickname,
            jobTitle: $this->jobTitle,
            department: $this->department,
            note: $this->note,
            simpleEmails: $this->simpleEmails,
            simplePhones: $this->simplePhones,
        );
    }
}
