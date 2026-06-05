<?php

use Bambamboole\LaravelDav\Dto\Contact\ContactEmailAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactPhoneNumber;
use Bambamboole\LaravelDav\Dto\ContactData;
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
        ->and($reparsed->emailAddresses)->toHaveCount(1)
        ->and($reparsed->emailAddresses[0]->value)->toBe('ada@example.com')
        ->and($reparsed->phoneNumbers[0]->value)->toBe('+491234567')
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

it('merges primary email and phone changes without dropping client-owned vcard content', function () {
    $existing = vcard(<<<'VCF'
        BEGIN:VCARD
        VERSION:3.0
        UID:merge-contact
        FN:Old Name
        N:Old;Name;;;
        EMAIL;TYPE=INTERNET:old@example.com
        EMAIL;TYPE=INTERNET:secondary@example.com
        TEL;TYPE=CELL:+1 000
        PHOTO;VALUE=uri:https://example.com/photo.jpg
        X-CUSTOM:keep-me
        END:VCARD
        VCF);

    $data = (new VCardParser)->parse($existing, 'merge-contact.vcf');
    $updated = new ContactData(
        uri: $data->uri,
        raw: $data->raw,
        etag: $data->etag,
        size: $data->size,
        uid: $data->uid,
        formattedName: 'New Name',
        givenName: 'New',
        familyName: 'Name',
        emailAddresses: [new ContactEmailAddress([
            'value' => 'new@example.com',
            'types' => ['INTERNET', 'WORK'],
        ])],
        phoneNumbers: [new ContactPhoneNumber([
            'value' => '+1 999',
            'types' => ['CELL'],
        ])],
    );

    $merged = (new VCardSerializer)->merge($existing, $updated);

    expect($merged)
        ->toContain('FN:New Name')
        ->toContain('EMAIL;TYPE=INTERNET:new@example.com')
        ->toContain('secondary@example.com')
        ->toContain('TEL;TYPE=CELL:+1 999')
        ->toContain('PHOTO;VALUE=uri:https://example.com/photo.jpg')
        ->toContain('X-CUSTOM:keep-me')
        ->not->toContain('old@example.com')
        ->not->toContain('FN:Old Name');
});

it('adds primary email and phone during merge when the existing vcard has none', function () {
    $existing = vcard(<<<'VCF'
        BEGIN:VCARD
        VERSION:3.0
        UID:merge-contact
        FN:Ada Lovelace
        N:Lovelace;Ada;;;
        X-CUSTOM:keep-me
        END:VCARD
        VCF);

    $data = (new VCardParser)->parse($existing, 'merge-contact.vcf');
    $updated = new ContactData(
        uri: $data->uri,
        raw: $data->raw,
        etag: $data->etag,
        size: $data->size,
        uid: $data->uid,
        formattedName: 'Ada Lovelace',
        givenName: 'Ada',
        familyName: 'Lovelace',
        emailAddresses: [new ContactEmailAddress([
            'label' => 'work',
            'value' => 'ada@example.com',
            'types' => ['INTERNET', 'WORK'],
        ])],
        phoneNumbers: [new ContactPhoneNumber([
            'label' => 'mobile',
            'value' => '+1 555',
            'types' => ['CELL'],
        ])],
    );

    $merged = (new VCardSerializer)->merge($existing, $updated);

    expect($merged)
        ->toContain('EMAIL;TYPE=INTERNET,WORK:ada@example.com')
        ->toContain('TEL;TYPE=CELL:+1 555')
        ->toContain('X-ABLABEL:_$!<Work>!$_')
        ->toContain('X-ABLABEL:mobile')
        ->toContain('X-CUSTOM:keep-me');
});

it('leaves existing emails and phones untouched when merge data does not carry replacements', function () {
    $existing = vcard(<<<'VCF'
        BEGIN:VCARD
        VERSION:3.0
        UID:merge-contact
        FN:Ada Lovelace
        N:Lovelace;Ada;;;
        EMAIL;TYPE=INTERNET:ada@example.com
        TEL;TYPE=CELL:+1 555
        END:VCARD
        VCF);

    $data = (new VCardParser)->parse($existing, 'merge-contact.vcf');
    $updated = new ContactData(
        uri: $data->uri,
        raw: $data->raw,
        etag: $data->etag,
        size: $data->size,
        uid: $data->uid,
        formattedName: 'Ada Byron',
        givenName: 'Ada',
        familyName: 'Byron',
    );

    $merged = (new VCardSerializer)->merge($existing, $updated);

    expect($merged)
        ->toContain('FN:Ada Byron')
        ->toContain('ada@example.com')
        ->toContain('+1 555');
});

it('keeps the vCard 4.0 version and unknown 4.0 properties when merging a typed edit', function () {
    $existing = vcard(<<<'VCF'
        BEGIN:VCARD
        VERSION:4.0
        UID:urn:uuid:merge-4
        FN:Old Name
        N:Old;Name;;;
        KIND:individual
        GENDER:F
        ANNIVERSARY:20100615
        EMAIL;PREF=1:old@example.com
        X-CUSTOM:keep-me
        END:VCARD
        VCF);

    $data = (new VCardParser)->parse($existing, 'merge-4.vcf');
    $updated = new ContactData(
        uri: $data->uri,
        raw: $data->raw,
        etag: $data->etag,
        size: $data->size,
        uid: $data->uid,
        formattedName: 'New Name',
        givenName: 'New',
        familyName: 'Name',
        emailAddresses: [new ContactEmailAddress(['value' => 'new@example.com'])],
    );

    $merged = (new VCardSerializer)->merge($existing, $updated);

    expect($merged)
        ->toContain('VERSION:4.0')
        ->toContain('FN:New Name')
        ->toContain('new@example.com')
        ->toContain('KIND:individual')
        ->toContain('GENDER:F')
        ->toContain('ANNIVERSARY:20100615')
        ->toContain('X-CUSTOM:keep-me')
        ->not->toContain('old@example.com')
        ->not->toContain('VERSION:3.0');
});
