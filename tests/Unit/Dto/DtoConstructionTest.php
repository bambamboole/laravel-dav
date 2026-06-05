<?php

use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Dto\Contact\ContactEmailAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactPhoneNumber;
use Bambamboole\LaravelDav\Dto\Contact\ContactPostalAddress;
use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Support\DtoFactory;
use Carbon\CarbonImmutable;

it('builds ContactData from a validated array shape', function (): void {
    $data = ContactData::fromArray([
        'formattedName' => 'Ada Lovelace',
        'givenName' => 'Ada',
        'familyName' => 'Lovelace',
        'emailAddresses' => [
            ['label' => 'work', 'value' => 'ada@example.com', 'types' => ['INTERNET', 'WORK']],
        ],
        'phoneNumbers' => [
            ['label' => 'mobile', 'value' => '+1 555 0100', 'types' => ['CELL'], 'isPreferred' => true],
        ],
        'addresses' => [
            ['label' => 'home', 'street' => '1 Example Street', 'city' => 'London', 'types' => ['HOME']],
        ],
    ]);

    expect($data->formattedName)->toBe('Ada Lovelace')
        ->and($data->givenName)->toBe('Ada')
        ->and($data->familyName)->toBe('Lovelace')
        ->and($data->emailAddresses[0])->toBeInstanceOf(ContactEmailAddress::class)
        ->and($data->emailAddresses[0]->value)->toBe('ada@example.com')
        ->and($data->phoneNumbers[0])->toBeInstanceOf(ContactPhoneNumber::class)
        ->and($data->phoneNumbers[0]->isPreferred)->toBeTrue()
        ->and($data->addresses[0])->toBeInstanceOf(ContactPostalAddress::class);
});

it('rebuilds ContactData through the DTO factory with overrides', function (): void {
    $email = new ContactEmailAddress([
        'label' => 'Work',
        'value' => 'ada@example.com',
        'types' => ['internet'],
    ]);

    $data = DtoFactory::contactData(new ContactData(
        uri: 'old.vcf',
        raw: 'BEGIN:VCARD',
        etag: 'old',
        size: 10,
        emailAddresses: [$email],
    ), [
        'uri' => 'new.vcf',
        'etag' => 'new',
        'size' => 20,
    ]);

    expect($data->uri)->toBe('new.vcf')
        ->and($data->etag)->toBe('new')
        ->and($data->size)->toBe(20)
        ->and($data->emailAddresses)->toHaveCount(1)
        ->and($data->emailAddresses[0])->toBe($email);
});

it('ignores simple email and phone input', function (): void {
    $data = ContactData::fromArray([
        'formattedName' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'phone' => '+1 555 0101',
        'emails' => ['ada@example.com', 'admin@example.com'],
        'phones' => ['+1 555 0100'],
    ]);

    expect($data->formattedName)->toBe('Grace Hopper')
        ->and($data->emailAddresses)->toBe([])
        ->and($data->phoneNumbers)->toBe([]);
});

it('builds CalendarObjectData from a validated array shape', function (): void {
    $data = CalendarObjectData::fromArray([
        'summary' => 'Sprint planning',
        'description' => 'Weekly planning',
        'startsAt' => '2026-01-01 09:00:00',
        'endsAt' => '2026-01-01 10:00:00',
        'isAllDay' => false,
        'timezone' => 'UTC',
    ]);

    expect($data->summary)->toBe('Sprint planning')
        ->and($data->description)->toBe('Weekly planning')
        ->and($data->startsAt)->toBeInstanceOf(CarbonImmutable::class)
        ->and($data->startsAt?->toDateTimeString())->toBe('2026-01-01 09:00:00')
        ->and($data->endsAt?->toDateTimeString())->toBe('2026-01-01 10:00:00')
        ->and($data->isAllDay)->toBeFalse()
        ->and($data->timezone)->toBe('UTC');
});
