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
use Bambamboole\LaravelDav\Support\DtoFactory;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class ContactData implements Arrayable, JsonSerializable
{
    /**
     * @param  array<int, ContactEmailAddress>  $emailAddresses
     * @param  array<int, ContactPhoneNumber>  $phoneNumbers
     * @param  array<int, ContactPostalAddress>  $addresses
     * @param  array<int, ContactUrl>  $urls
     * @param  array<int, ContactInstantMessage>  $instantMessages
     * @param  array<int, ContactSocialProfile>  $socialProfiles
     * @param  array<int, ContactDate>  $dates
     * @param  array<int, ContactRelation>  $relations
     * @param  array<int, ContactVCardExtension>  $extensions
     * @param  array<int, ContactPronoun>  $pronouns
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
        public array $emailAddresses = [],
        public array $phoneNumbers = [],
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
        public ?string $phoneticGivenName = null,
        public ?string $phoneticMiddleName = null,
        public ?string $phoneticFamilyName = null,
        public ?string $phoneticOrganization = null,
        public ?string $previousFamilyName = null,
        public ?string $nameSuffix = null,
        public ?string $nickname = null,
        public ?string $jobTitle = null,
        public ?string $department = null,
        public ?string $note = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return DtoFactory::contactData($data);
    }

    public function withStorageMeta(string $uri, string $etag, int $size): self
    {
        return DtoFactory::contactData($this, [
            'uri' => $uri,
            'etag' => $etag,
            'size' => $size,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return DtoFactory::dataArray($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
