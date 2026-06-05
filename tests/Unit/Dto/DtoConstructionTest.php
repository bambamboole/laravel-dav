<?php

use Bambamboole\LaravelDav\Dto\AddressBookData;
use Bambamboole\LaravelDav\Dto\CalendarData;
use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Dto\Contact\ContactEmailAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactPhoneNumber;
use Bambamboole\LaravelDav\Dto\Contact\ContactPostalAddress;
use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Dto\PrincipalData;
use Carbon\CarbonImmutable;

it('keeps raw canonical and parsed fields optional', function () {
    $dto = new CalendarObjectData(uri: 'x.ics', raw: 'BEGIN:VCALENDAR', etag: 'abc', size: 15);

    expect($dto->raw)->toContain('VCALENDAR')
        ->and($dto->uri)->toBe('x.ics')
        ->and($dto->etag)->toBe('abc')
        ->and($dto->size)->toBe(15)
        ->and($dto->summary)->toBeNull()
        ->and($dto->description)->toBeNull()
        ->and($dto->location)->toBeNull()
        ->and($dto->status)->toBeNull()
        ->and($dto->url)->toBeNull()
        ->and($dto->uid)->toBeNull()
        ->and($dto->componentType)->toBeNull()
        ->and($dto->startsAt)->toBeNull()
        ->and($dto->endsAt)->toBeNull()
        ->and($dto->isAllDay)->toBeFalse()
        ->and($dto->timezone)->toBeNull();
});

it('withStorageMeta preserves parsed fields', function () {
    $startsAt = CarbonImmutable::parse('2026-01-01T10:00:00Z');

    $dto = new CalendarObjectData(
        uri: '',
        raw: 'R',
        etag: '',
        size: 0,
        summary: 'Hi',
        startsAt: $startsAt,
    );

    $stamped = $dto->withStorageMeta('e.ics', 'tag', 42);

    expect($stamped->uri)->toBe('e.ics')
        ->and($stamped->etag)->toBe('tag')
        ->and($stamped->size)->toBe(42)
        ->and($stamped->summary)->toBe('Hi')
        ->and($stamped->startsAt)->toEqual($startsAt)
        ->and($stamped->raw)->toBe('R');
});

it('withStorageMeta returns a new instance', function () {
    $dto = new CalendarObjectData(uri: 'a.ics', raw: 'R', etag: 'e', size: 1);
    $stamped = $dto->withStorageMeta('b.ics', 'f', 2);

    expect($stamped)->not->toBe($dto);
});

it('ContactData accepts typed arrays of Contact value objects', function () {
    $email = new ContactEmailAddress([
        'label' => 'Work',
        'value' => 'hello@example.com',
        'types' => ['work'],
        'is_preferred' => false,
        'group' => null,
    ]);

    $dto = new ContactData(
        uri: 'contact.vcf',
        raw: 'BEGIN:VCARD',
        etag: 'etag1',
        size: 11,
        emails: [$email],
    );

    expect($dto->emails)->toHaveCount(1)
        ->and($dto->emails[0])->toBeInstanceOf(ContactEmailAddress::class)
        ->and($dto->emails[0]->value)->toBe('hello@example.com');
});

it('builds ContactData from a validated array shape', function (): void {
    $data = ContactData::fromArray([
        'full_name' => 'Ada Lovelace',
        'given_name' => 'Ada',
        'family_name' => 'Lovelace',
        'email_addresses' => [
            ['label' => 'work', 'value' => 'ada@example.com', 'types' => ['INTERNET', 'WORK']],
        ],
        'phone_numbers' => [
            ['label' => 'mobile', 'value' => '+1 555 0100', 'types' => ['CELL'], 'is_preferred' => true],
        ],
        'addresses' => [
            ['label' => 'home', 'street' => '1 Example Street', 'city' => 'London', 'types' => ['HOME']],
        ],
    ]);

    expect($data->formattedName)->toBe('Ada Lovelace')
        ->and($data->givenName)->toBe('Ada')
        ->and($data->familyName)->toBe('Lovelace')
        ->and($data->emails[0])->toBeInstanceOf(ContactEmailAddress::class)
        ->and($data->emails[0]->value)->toBe('ada@example.com')
        ->and($data->phones[0])->toBeInstanceOf(ContactPhoneNumber::class)
        ->and($data->phones[0]->isPreferred)->toBeTrue()
        ->and($data->addresses[0])->toBeInstanceOf(ContactPostalAddress::class);
});

it('builds ContactData from simple contact fields', function (): void {
    $data = ContactData::fromArray([
        'full_name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'phone' => '+1 555 0101',
    ]);

    expect($data->formattedName)->toBe('Grace Hopper')
        ->and($data->emails[0])->toBeInstanceOf(ContactEmailAddress::class)
        ->and($data->emails[0]->value)->toBe('grace@example.com')
        ->and($data->phones[0])->toBeInstanceOf(ContactPhoneNumber::class)
        ->and($data->phones[0]->value)->toBe('+1 555 0101');
});

it('normalizes simple contact lists into typed email and phone values', function (): void {
    $data = ContactData::fromArray([
        'emails' => ['ada@example.com', 'admin@example.com'],
        'phones' => ['+1 555 0100'],
    ]);

    expect($data->emails)->toHaveCount(2)
        ->and($data->emails[0])->toBeInstanceOf(ContactEmailAddress::class)
        ->and($data->emails[0]->value)->toBe('ada@example.com')
        ->and($data->emails[0]->types)->toBe(['INTERNET'])
        ->and($data->emails[1]->value)->toBe('admin@example.com')
        ->and($data->phones)->toHaveCount(1)
        ->and($data->phones[0])->toBeInstanceOf(ContactPhoneNumber::class)
        ->and($data->phones[0]->value)->toBe('+1 555 0100')
        ->and($data->phones[0]->types)->toBe(['CELL']);
});

it('builds CalendarObjectData from a validated array shape', function (): void {
    $data = CalendarObjectData::fromArray([
        'summary' => 'Sprint planning',
        'description' => 'Weekly planning',
        'starts_at' => '2026-01-01 09:00:00',
        'ends_at' => '2026-01-01 10:00:00',
        'is_all_day' => false,
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

it('ContactData withStorageMeta preserves emails array', function () {
    $email = new ContactEmailAddress([
        'label' => null,
        'value' => 'keep@example.com',
        'types' => [],
        'is_preferred' => false,
        'group' => null,
    ]);

    $dto = new ContactData(
        uri: '',
        raw: 'BEGIN:VCARD',
        etag: '',
        size: 0,
        emails: [$email],
    );

    $stamped = $dto->withStorageMeta('new.vcf', 'newtag', 99);

    expect($stamped->uri)->toBe('new.vcf')
        ->and($stamped->etag)->toBe('newtag')
        ->and($stamped->size)->toBe(99)
        ->and($stamped->emails)->toHaveCount(1)
        ->and($stamped->emails[0]->value)->toBe('keep@example.com');
});

it('CalendarData constructs with expected defaults', function () {
    $dto = new CalendarData(uri: 'cal/');

    expect($dto->uri)->toBe('cal/')
        ->and($dto->displayName)->toBeNull()
        ->and($dto->description)->toBeNull()
        ->and($dto->color)->toBeNull()
        ->and($dto->timezone)->toBeNull()
        ->and($dto->components)->toBe([])
        ->and($dto->syncToken)->toBe(1);
});

it('AddressBookData constructs with expected defaults', function () {
    $dto = new AddressBookData(uri: 'ab/');

    expect($dto->uri)->toBe('ab/')
        ->and($dto->displayName)->toBeNull()
        ->and($dto->description)->toBeNull()
        ->and($dto->syncToken)->toBe(1);
});

it('PrincipalData constructs with expected defaults', function () {
    $dto = new PrincipalData(uri: 'principals/user', id: 42);

    expect($dto->uri)->toBe('principals/user')
        ->and($dto->id)->toBe(42)
        ->and($dto->displayName)->toBeNull()
        ->and($dto->email)->toBeNull();
});

it('PrincipalData accepts string id', function () {
    $dto = new PrincipalData(uri: 'principals/user', id: 'uuid-abc');

    expect($dto->id)->toBe('uuid-abc');
});
