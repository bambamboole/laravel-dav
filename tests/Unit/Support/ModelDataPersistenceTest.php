<?php

use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Dto\Contact\ContactEmailAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactPhoneNumber;
use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Models\DavCard;
use Carbon\CarbonImmutable;

it('persists contact data as a dav card row', function (): void {
    $addressBook = DavAddressBook::factory()->create();
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

    $card = DavCard::createFromData($addressBook, 'contact.vcf', $data);

    expect($card->full_name)->toBe('Ada Lovelace')
        ->and($card->given_name)->toBe('Ada')
        ->and($card->family_name)->toBe('Lovelace')
        ->and($card->email_addresses->first())->toBeInstanceOf(ContactEmailAddress::class)
        ->and($card->phone_numbers->first())->toBeInstanceOf(ContactPhoneNumber::class);
});

it('persists calendar object data as a dav calendar object row', function (): void {
    $calendar = DavCalendar::factory()->create();
    $startsAt = CarbonImmutable::parse('2026-01-01 09:00:00', 'UTC');
    $endsAt = CarbonImmutable::parse('2026-01-01 10:00:00', 'UTC');

    $object = DavCalendarObject::createFromData($calendar, 'event.ics', new CalendarObjectData(
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

    expect($object->component_type)->toBe('VEVENT')
        ->and($object->summary)->toBe('Sprint planning')
        ->and($object->starts_at?->timestamp)->toBe($startsAt->timestamp)
        ->and($object->ends_at?->timestamp)->toBe($endsAt->timestamp)
        ->and($object->timezone)->toBe('UTC');
});
