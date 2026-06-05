<?php

use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Dto\Contact\ContactEmailAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactPhoneNumber;
use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Support\CalendarObjectProjection;
use Bambamboole\LaravelDav\Support\ContactCardProjection;
use Carbon\CarbonImmutable;

it('maps contact data to dav card attributes', function (): void {
    $data = new ContactData(
        uri: 'contact.vcf',
        raw: '',
        etag: '',
        size: 0,
        uid: 'contact',
        formattedName: 'Ada Lovelace',
        givenName: 'Ada',
        familyName: 'Lovelace',
        emails: [new ContactEmailAddress(['label' => 'work', 'value' => 'ada@example.com', 'types' => ['INTERNET', 'WORK']])],
        phones: [new ContactPhoneNumber(['label' => 'mobile', 'value' => '+1 555 0100', 'types' => ['CELL']])],
    );

    $attributes = (new ContactCardProjection)->attributesFromData($data);

    expect($attributes['full_name'])->toBe('Ada Lovelace')
        ->and($attributes['given_name'])->toBe('Ada')
        ->and($attributes['family_name'])->toBe('Lovelace')
        ->and($attributes['emails'])->toBe(['ada@example.com'])
        ->and($attributes['phones'])->toBe(['+1 555 0100'])
        ->and($attributes['email_addresses'][0])->toBeInstanceOf(ContactEmailAddress::class)
        ->and($attributes['phone_numbers'][0])->toBeInstanceOf(ContactPhoneNumber::class);
});

it('maps calendar object data to dav calendar object attributes', function (): void {
    $startsAt = CarbonImmutable::parse('2026-01-01 09:00:00', 'UTC');
    $endsAt = CarbonImmutable::parse('2026-01-01 10:00:00', 'UTC');

    $attributes = (new CalendarObjectProjection)->attributesFromData(new CalendarObjectData(
        uri: 'event.ics',
        raw: '',
        etag: '',
        size: 0,
        uid: 'event',
        summary: 'Sprint planning',
        startsAt: $startsAt,
        endsAt: $endsAt,
        timezone: 'UTC',
    ));

    expect($attributes['component_type'])->toBe('VEVENT')
        ->and($attributes['summary'])->toBe('Sprint planning')
        ->and($attributes['starts_at'])->toBe($startsAt)
        ->and($attributes['ends_at'])->toBe($endsAt)
        ->and($attributes['timezone'])->toBe('UTC');
});
