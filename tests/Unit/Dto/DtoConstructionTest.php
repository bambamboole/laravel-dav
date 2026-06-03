<?php

use Bambamboole\LaravelDav\Dto\AddressBookData;
use Bambamboole\LaravelDav\Dto\CalendarData;
use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Dto\Contact\ContactEmailAddress;
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
