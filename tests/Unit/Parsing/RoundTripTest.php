<?php

use Bambamboole\LaravelDav\Parsing\CalendarObjectParser;
use Bambamboole\LaravelDav\Parsing\CalendarObjectSerializer;
use Bambamboole\LaravelDav\Parsing\VCardParser;
use Bambamboole\LaravelDav\Parsing\VCardSerializer;

it('round-trips a calendar event through parse and serialize', function () {
    $payload = calendarObjectPayload('VEVENT', [
        'UID' => 'rt-event',
        'SUMMARY' => 'Quarterly Planning',
        'DESCRIPTION' => 'Plan the next quarter',
        'LOCATION' => 'Room 7',
        'STATUS' => 'CONFIRMED',
        'URL' => 'https://example.com/event',
        'DTSTART' => '20260603T070000Z',
        'DTEND' => '20260603T083000Z',
    ]);

    $parsed = (new CalendarObjectParser)->parse($payload, 'rt-event.ics');
    $serialized = (new CalendarObjectSerializer)->serialize($parsed);
    $reparsed = (new CalendarObjectParser)->parse($serialized, 'rt-event.ics');

    expect($reparsed->uid)->toBe($parsed->uid)
        ->and($reparsed->componentType)->toBe('VEVENT')
        ->and($reparsed->summary)->toBe('Quarterly Planning')
        ->and($reparsed->description)->toBe('Plan the next quarter')
        ->and($reparsed->location)->toBe('Room 7')
        ->and($reparsed->status)->toBe('CONFIRMED')
        ->and($reparsed->url)->toBe('https://example.com/event')
        ->and($reparsed->startsAt?->toIso8601String())->toBe($parsed->startsAt?->toIso8601String())
        ->and($reparsed->endsAt?->toIso8601String())->toBe($parsed->endsAt?->toIso8601String())
        ->and($reparsed->isAllDay)->toBe($parsed->isAllDay);
});

it('round-trips an all-day event preserving the all-day flag', function () {
    $payload = calendarObjectPayload('VEVENT', [
        'UID' => 'rt-allday',
        'SUMMARY' => 'Holiday',
        'DTSTART' => ['value' => '20260603', 'parameters' => ['VALUE' => 'DATE']],
        'DTEND' => ['value' => '20260604', 'parameters' => ['VALUE' => 'DATE']],
    ]);

    $parsed = (new CalendarObjectParser)->parse($payload);
    $serialized = (new CalendarObjectSerializer)->serialize($parsed);
    $reparsed = (new CalendarObjectParser)->parse($serialized);

    expect($reparsed->isAllDay)->toBeTrue()
        ->and($reparsed->startsAt?->toDateString())->toBe('2026-06-03')
        ->and($reparsed->endsAt?->toDateString())->toBe('2026-06-04');
});

it('round-trips a vcard through parse and serialize', function () {
    $payload = contactCardPayload([
        'UID' => 'rt-contact',
        'FN' => 'Ada Lovelace',
        'N' => ['value' => ['Lovelace', 'Ada', 'Augusta', 'Ms', 'PhD']],
        'NICKNAME' => 'Countess',
        'ORG' => ['value' => ['Analytical Engines', 'Research']],
        'TITLE' => 'Mathematician',
        'NOTE' => 'First programmer',
        'EMAIL' => [
            ['value' => 'ada@example.com', 'parameters' => ['TYPE' => 'work']],
        ],
        'TEL' => [
            ['value' => '+491234567', 'parameters' => ['TYPE' => 'cell']],
        ],
        'ADR' => [
            ['value' => ['', '', '1 Engine St', 'London', '', 'EC1', 'UK'], 'parameters' => ['TYPE' => 'home']],
        ],
    ]);

    $parsed = (new VCardParser)->parse($payload, 'rt-contact.vcf');
    $serialized = (new VCardSerializer)->serialize($parsed);
    $reparsed = (new VCardParser)->parse($serialized, 'rt-contact.vcf');

    expect($reparsed->uid)->toBe('rt-contact')
        ->and($reparsed->formattedName)->toBe('Ada Lovelace')
        ->and($reparsed->givenName)->toBe('Ada')
        ->and($reparsed->familyName)->toBe('Lovelace')
        ->and($reparsed->middleName)->toBe('Augusta')
        ->and($reparsed->namePrefix)->toBe('Ms')
        ->and($reparsed->nameSuffix)->toBe('PhD')
        ->and($reparsed->nickname)->toBe('Countess')
        ->and($reparsed->organization)->toBe('Analytical Engines')
        ->and($reparsed->department)->toBe('Research')
        ->and($reparsed->jobTitle)->toBe('Mathematician')
        ->and($reparsed->note)->toBe('First programmer')
        ->and($reparsed->emails)->toHaveCount(1)
        ->and($reparsed->emails[0]->value)->toBe('ada@example.com')
        ->and($reparsed->phones[0]->value)->toBe('+491234567')
        ->and($reparsed->addresses)->toHaveCount(1)
        ->and($reparsed->addresses[0]->street)->toBe('1 Engine St')
        ->and($reparsed->addresses[0]->city)->toBe('London');
});

it('round-trips phonetic and maiden-name fields through parse and serialize', function () {
    $payload = vcard(<<<'VCF'
        BEGIN:VCARD
        VERSION:3.0
        UID:rt-phonetic
        FN:Ada Lovelace
        N:Lovelace;Ada;;;
        X-PHONETIC-FIRST-NAME:AY-dah
        X-PHONETIC-MIDDLE-NAME:aw-GUS-tah
        X-PHONETIC-LAST-NAME:LUV-lays
        X-PHONETIC-ORG:an-uh-LIT-ik-ul
        X-MAIDEN-NAME:Byron
        END:VCARD
        VCF);

    $parsed = (new VCardParser)->parse($payload, 'rt-phonetic.vcf');
    $serialized = (new VCardSerializer)->serialize($parsed);
    $reparsed = (new VCardParser)->parse($serialized, 'rt-phonetic.vcf');

    expect($reparsed->phoneticGivenName)->toBe('AY-dah')
        ->and($reparsed->phoneticMiddleName)->toBe('aw-GUS-tah')
        ->and($reparsed->phoneticFamilyName)->toBe('LUV-lays')
        ->and($reparsed->phoneticOrganization)->toBe('an-uh-LIT-ik-ul')
        ->and($reparsed->previousFamilyName)->toBe('Byron');
});

it('round-trips an organization vcard preserving the show-as company flag', function () {
    $payload = contactCardPayload([
        'UID' => 'rt-org',
        'FN' => 'Analytical Engines',
        'ORG' => ['value' => ['Analytical Engines']],
        'X-ABShowAs' => 'COMPANY',
    ]);

    $parsed = (new VCardParser)->parse($payload);
    $serialized = (new VCardSerializer)->serialize($parsed);
    $reparsed = (new VCardParser)->parse($serialized);

    expect($parsed->contactType)->toBe('organization')
        ->and($reparsed->contactType)->toBe('organization');
});
