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
        public array $simpleEmails = [],
        public array $simplePhones = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uri: self::string($data, 'uri'),
            raw: self::string($data, 'raw'),
            etag: self::string($data, 'etag'),
            size: self::int($data, 'size'),
            uid: self::nullableString($data, 'uid'),
            formattedName: self::nullableString($data, 'formatted_name') ?? self::nullableString($data, 'formattedName') ?? self::nullableString($data, 'full_name'),
            givenName: self::nullableString($data, 'given_name') ?? self::nullableString($data, 'givenName'),
            familyName: self::nullableString($data, 'family_name') ?? self::nullableString($data, 'familyName'),
            organization: self::nullableString($data, 'organization'),
            contactType: self::string($data, 'contact_type') ?: self::string($data, 'contactType', 'person'),
            birthday: self::contactDate($data['birthday'] ?? null),
            emails: self::emailAddresses($data),
            phones: self::phoneNumbers($data),
            addresses: self::typedList($data['addresses'] ?? [], fn (array $row): ContactPostalAddress => new ContactPostalAddress($row)),
            urls: self::typedList($data['urls'] ?? [], fn (array $row): ContactUrl => new ContactUrl($row)),
            instantMessages: self::typedList($data['instant_messages'] ?? $data['instantMessages'] ?? [], fn (array $row): ContactInstantMessage => new ContactInstantMessage($row)),
            socialProfiles: self::typedList($data['social_profiles'] ?? $data['socialProfiles'] ?? [], fn (array $row): ContactSocialProfile => new ContactSocialProfile($row)),
            dates: self::typedList($data['dates'] ?? [], fn (array $row): ContactDate => new ContactDate($row)),
            relations: self::typedList($data['relations'] ?? [], fn (array $row): ContactRelation => new ContactRelation($row)),
            extensions: self::typedList($data['vcard_extensions'] ?? $data['extensions'] ?? [], fn (array $row): ContactVCardExtension => new ContactVCardExtension($row)),
            pronouns: self::typedList($data['pronouns'] ?? [], fn (array $row): ContactPronoun => new ContactPronoun($row)),
            namePrefix: self::nullableString($data, 'name_prefix') ?? self::nullableString($data, 'namePrefix'),
            middleName: self::nullableString($data, 'middle_name') ?? self::nullableString($data, 'middleName'),
            phoneticGivenName: self::nullableString($data, 'phonetic_given_name') ?? self::nullableString($data, 'phoneticGivenName'),
            phoneticMiddleName: self::nullableString($data, 'phonetic_middle_name') ?? self::nullableString($data, 'phoneticMiddleName'),
            phoneticFamilyName: self::nullableString($data, 'phonetic_family_name') ?? self::nullableString($data, 'phoneticFamilyName'),
            phoneticOrganization: self::nullableString($data, 'phonetic_organization') ?? self::nullableString($data, 'phoneticOrganization'),
            previousFamilyName: self::nullableString($data, 'previous_family_name') ?? self::nullableString($data, 'previousFamilyName'),
            nameSuffix: self::nullableString($data, 'name_suffix') ?? self::nullableString($data, 'nameSuffix'),
            nickname: self::nullableString($data, 'nickname'),
            jobTitle: self::nullableString($data, 'job_title') ?? self::nullableString($data, 'jobTitle'),
            department: self::nullableString($data, 'department'),
            note: self::nullableString($data, 'note'),
            simpleEmails: self::stringList($data['emails'] ?? []),
            simplePhones: self::stringList($data['phones'] ?? []),
        );
    }

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
            phoneticGivenName: $this->phoneticGivenName,
            phoneticMiddleName: $this->phoneticMiddleName,
            phoneticFamilyName: $this->phoneticFamilyName,
            phoneticOrganization: $this->phoneticOrganization,
            previousFamilyName: $this->previousFamilyName,
            nameSuffix: $this->nameSuffix,
            nickname: $this->nickname,
            jobTitle: $this->jobTitle,
            department: $this->department,
            note: $this->note,
            simpleEmails: $this->simpleEmails,
            simplePhones: $this->simplePhones,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function string(array $data, string $key, string $default = ''): string
    {
        return self::nullableString($data, $key) ?? $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private static function contactDate(mixed $value): ?ContactDate
    {
        if ($value instanceof ContactDate) {
            return $value;
        }

        return is_array($value) ? new ContactDate($value) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, ContactEmailAddress>
     */
    private static function emailAddresses(array $data): array
    {
        $emails = self::typedList($data['email_addresses'] ?? $data['emails_typed'] ?? [], fn (array $row): ContactEmailAddress => new ContactEmailAddress($row));

        if ($emails !== []) {
            return $emails;
        }

        $email = self::nullableString($data, 'email');

        return $email === null ? [] : [
            new ContactEmailAddress([
                'label' => 'work',
                'value' => $email,
                'types' => ['INTERNET', 'WORK'],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, ContactPhoneNumber>
     */
    private static function phoneNumbers(array $data): array
    {
        $phones = self::typedList($data['phone_numbers'] ?? $data['phones_typed'] ?? [], fn (array $row): ContactPhoneNumber => new ContactPhoneNumber($row));

        if ($phones !== []) {
            return $phones;
        }

        $phone = self::nullableString($data, 'phone');

        return $phone === null ? [] : [
            new ContactPhoneNumber([
                'label' => 'mobile',
                'value' => $phone,
                'types' => ['CELL'],
                'is_preferred' => true,
            ]),
        ];
    }

    /**
     * @template TValue
     *
     * @param  callable(array<string, mixed>): TValue  $factory
     * @return array<int, TValue>
     */
    private static function typedList(mixed $value, callable $factory): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): mixed => $factory($row))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_string($item) || is_numeric($item))
            ->map(fn (mixed $item): string => (string) $item)
            ->filter(fn (string $item): bool => $item !== '')
            ->values()
            ->all();
    }
}
