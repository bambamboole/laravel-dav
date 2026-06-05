<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Dto\CalendarObjectData;
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
use Bambamboole\LaravelDav\Dto\ContactData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;

final class DtoFactory
{
    /**
     * @param  ContactData|array<string, mixed>  $data
     * @param  array<string, mixed>  $overrides
     */
    public static function contactData(ContactData|array $data, array $overrides = []): ContactData
    {
        $data = self::data($data, $overrides);

        return new ContactData(
            uri: self::string($data, 'uri'),
            raw: self::string($data, 'raw'),
            etag: self::string($data, 'etag'),
            size: self::int($data, 'size'),
            uid: self::nullableString($data, 'uid'),
            formattedName: self::nullableString($data, 'formattedName'),
            givenName: self::nullableString($data, 'givenName'),
            familyName: self::nullableString($data, 'familyName'),
            organization: self::nullableString($data, 'organization'),
            contactType: self::string($data, 'contactType', 'person'),
            birthday: self::contactDate($data['birthday'] ?? null),
            emailAddresses: self::typedList($data['emailAddresses'] ?? [], ContactEmailAddress::class),
            phoneNumbers: self::typedList($data['phoneNumbers'] ?? [], ContactPhoneNumber::class),
            addresses: self::typedList($data['addresses'] ?? [], ContactPostalAddress::class),
            urls: self::typedList($data['urls'] ?? [], ContactUrl::class),
            instantMessages: self::typedList($data['instantMessages'] ?? [], ContactInstantMessage::class),
            socialProfiles: self::typedList($data['socialProfiles'] ?? [], ContactSocialProfile::class),
            dates: self::typedList($data['dates'] ?? [], ContactDate::class),
            relations: self::typedList($data['relations'] ?? [], ContactRelation::class),
            extensions: self::typedList($data['extensions'] ?? [], ContactVCardExtension::class),
            pronouns: self::typedList($data['pronouns'] ?? [], ContactPronoun::class),
            namePrefix: self::nullableString($data, 'namePrefix'),
            middleName: self::nullableString($data, 'middleName'),
            phoneticGivenName: self::nullableString($data, 'phoneticGivenName'),
            phoneticMiddleName: self::nullableString($data, 'phoneticMiddleName'),
            phoneticFamilyName: self::nullableString($data, 'phoneticFamilyName'),
            phoneticOrganization: self::nullableString($data, 'phoneticOrganization'),
            previousFamilyName: self::nullableString($data, 'previousFamilyName'),
            nameSuffix: self::nullableString($data, 'nameSuffix'),
            nickname: self::nullableString($data, 'nickname'),
            jobTitle: self::nullableString($data, 'jobTitle'),
            department: self::nullableString($data, 'department'),
            note: self::nullableString($data, 'note'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function contactDataArray(ContactData $data): array
    {
        return [
            'uri' => $data->uri,
            'raw' => $data->raw,
            'etag' => $data->etag,
            'size' => $data->size,
            'uid' => $data->uid,
            'formattedName' => $data->formattedName,
            'givenName' => $data->givenName,
            'familyName' => $data->familyName,
            'organization' => $data->organization,
            'contactType' => $data->contactType,
            'birthday' => self::arrayValue($data->birthday),
            'emailAddresses' => self::arrayList($data->emailAddresses),
            'phoneNumbers' => self::arrayList($data->phoneNumbers),
            'addresses' => self::arrayList($data->addresses),
            'urls' => self::arrayList($data->urls),
            'instantMessages' => self::arrayList($data->instantMessages),
            'socialProfiles' => self::arrayList($data->socialProfiles),
            'dates' => self::arrayList($data->dates),
            'relations' => self::arrayList($data->relations),
            'extensions' => self::arrayList($data->extensions),
            'pronouns' => self::arrayList($data->pronouns),
            'namePrefix' => $data->namePrefix,
            'middleName' => $data->middleName,
            'phoneticGivenName' => $data->phoneticGivenName,
            'phoneticMiddleName' => $data->phoneticMiddleName,
            'phoneticFamilyName' => $data->phoneticFamilyName,
            'phoneticOrganization' => $data->phoneticOrganization,
            'previousFamilyName' => $data->previousFamilyName,
            'nameSuffix' => $data->nameSuffix,
            'nickname' => $data->nickname,
            'jobTitle' => $data->jobTitle,
            'department' => $data->department,
            'note' => $data->note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function contactStorageData(ContactData $data): array
    {
        return array_diff_key(self::contactDataArray($data), array_flip(['uri', 'raw', 'etag', 'size']));
    }

    /**
     * @param  CalendarObjectData|array<string, mixed>  $data
     * @param  array<string, mixed>  $overrides
     */
    public static function calendarObjectData(CalendarObjectData|array $data, array $overrides = []): CalendarObjectData
    {
        $data = self::data($data, $overrides);

        return new CalendarObjectData(
            uri: self::string($data, 'uri'),
            raw: self::string($data, 'raw'),
            etag: self::string($data, 'etag'),
            size: self::int($data, 'size'),
            uid: self::nullableString($data, 'uid'),
            componentType: self::nullableString($data, 'componentType'),
            summary: self::nullableString($data, 'summary'),
            description: self::nullableString($data, 'description'),
            location: self::nullableString($data, 'location'),
            status: self::nullableString($data, 'status'),
            url: self::nullableString($data, 'url'),
            startsAt: self::dateTime($data['startsAt'] ?? null, self::nullableString($data, 'timezone')),
            endsAt: self::dateTime($data['endsAt'] ?? null, self::nullableString($data, 'timezone')),
            isAllDay: self::bool($data['isAllDay'] ?? false),
            timezone: self::nullableString($data, 'timezone'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function calendarObjectDataArray(CalendarObjectData $data): array
    {
        return [
            'uri' => $data->uri,
            'raw' => $data->raw,
            'etag' => $data->etag,
            'size' => $data->size,
            'uid' => $data->uid,
            'componentType' => $data->componentType,
            'summary' => $data->summary,
            'description' => $data->description,
            'location' => $data->location,
            'status' => $data->status,
            'url' => $data->url,
            'startsAt' => $data->startsAt?->toJSON(),
            'endsAt' => $data->endsAt?->toJSON(),
            'isAllDay' => $data->isAllDay,
            'timezone' => $data->timezone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function calendarObjectStorageData(CalendarObjectData $data): array
    {
        return array_diff_key(self::calendarObjectDataArray($data), array_flip([
            'uri',
            'raw',
            'etag',
            'size',
            'uid',
            'componentType',
            'startsAt',
            'endsAt',
            'isAllDay',
            'timezone',
        ]));
    }

    /**
     * @param  ContactData|CalendarObjectData|array<string, mixed>  $data
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function data(ContactData|CalendarObjectData|array $data, array $overrides): array
    {
        if (! is_array($data)) {
            $data = get_object_vars($data);
        }

        return array_replace($data, $overrides);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function nullableString(array $data, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
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

    private static function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private static function contactDate(mixed $value): ?ContactDate
    {
        if ($value instanceof ContactDate) {
            return $value;
        }

        return is_array($value) ? new ContactDate($value) : null;
    }

    /**
     * @return array<string, mixed>|mixed|null
     */
    private static function arrayValue(mixed $value): mixed
    {
        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        return $value;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, mixed>
     */
    private static function arrayList(array $values): array
    {
        return array_map(self::arrayValue(...), $values);
    }

    /**
     * @template TValue
     *
     * @param  class-string<TValue>  $class
     * @return array<int, TValue>
     */
    private static function typedList(mixed $value, string $class): array
    {
        if (! is_iterable($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $row): bool => $row instanceof $class || is_array($row))
            ->map(fn (mixed $row): mixed => $row instanceof $class ? $row : new $class($row))
            ->values()
            ->all();
    }

    private static function dateTime(mixed $value, ?string $timezone): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        return CarbonImmutable::parse((string) $value, $timezone);
    }
}
