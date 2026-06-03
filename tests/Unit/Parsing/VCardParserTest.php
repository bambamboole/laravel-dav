<?php

use Bambamboole\LaravelDav\Dto\Contact\ContactEmailAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactPhoneNumber;
use Bambamboole\LaravelDav\Dto\Contact\ContactPostalAddress;
use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Parsing\VCardParser;

it('parses contact fields', function () {
    $payload = "BEGIN:VCARD\r\nVERSION:3.0\r\nPRODID:-//Life OS//Tests//EN\r\nUID:contact-1\r\nFN:Ada Lovelace\r\nN:Lovelace;Ada;;;\r\nEMAIL;TYPE=work:ada@example.com\r\nTEL;TYPE=cell:+491234567\r\nORG:Analytical Engines\r\nEND:VCARD\r\n";

    $data = (new VCardParser)->parse($payload, 'contact-1.vcf');

    expect($data)->toBeInstanceOf(ContactData::class)
        ->and($data->uri)->toBe('contact-1.vcf')
        ->and($data->raw)->toBe($payload)
        ->and($data->etag)->toBe(sha1($payload))
        ->and($data->size)->toBe(strlen($payload))
        ->and($data->uid)->toBe('contact-1')
        ->and($data->formattedName)->toBe('Ada Lovelace')
        ->and($data->givenName)->toBe('Ada')
        ->and($data->familyName)->toBe('Lovelace')
        ->and($data->organization)->toBe('Analytical Engines')
        ->and($data->simpleEmails)->toBe(['ada@example.com'])
        ->and($data->simplePhones)->toBe(['+491234567'])
        ->and($data->emails)->toHaveCount(1)
        ->and($data->emails[0])->toBeInstanceOf(ContactEmailAddress::class)
        ->and($data->emails[0]->value)->toBe('ada@example.com')
        ->and($data->emails[0]->types)->toBe(['work'])
        ->and($data->phones[0])->toBeInstanceOf(ContactPhoneNumber::class)
        ->and($data->phones[0]->value)->toBe('+491234567');
});

it('parses multiple emails and phones', function () {
    $payload = contactCardPayload([
        'UID' => 'contact-2',
        'FN' => 'Grace Hopper',
        'EMAIL' => [
            ['value' => 'grace@example.com', 'parameters' => ['TYPE' => 'work']],
            ['value' => 'hopper@example.net', 'parameters' => ['TYPE' => 'home']],
        ],
        'TEL' => [
            ['value' => '+491111111', 'parameters' => ['TYPE' => 'cell']],
            ['value' => '+492222222', 'parameters' => ['TYPE' => 'work']],
        ],
    ]);

    $data = (new VCardParser)->parse($payload);

    expect($data->simpleEmails)->toBe(['grace@example.com', 'hopper@example.net'])
        ->and($data->simplePhones)->toBe(['+491111111', '+492222222'])
        ->and($data->emails)->toHaveCount(2)
        ->and($data->emails[1]->value)->toBe('hopper@example.net')
        ->and($data->emails[1]->types)->toBe(['home'])
        ->and($data->phones)->toHaveCount(2);
});

it('parses postal addresses', function () {
    $payload = contactCardPayload([
        'UID' => 'contact-3',
        'FN' => 'Katherine Johnson',
        'ADR' => [
            ['value' => ['', '', '100 Orbit Way', 'Hampton', 'VA', '23666', 'USA'], 'parameters' => ['TYPE' => 'home']],
        ],
    ]);

    $data = (new VCardParser)->parse($payload);

    expect($data->addresses)->toHaveCount(1)
        ->and($data->addresses[0])->toBeInstanceOf(ContactPostalAddress::class)
        ->and($data->addresses[0]->street)->toBe('100 Orbit Way')
        ->and($data->addresses[0]->city)->toBe('Hampton')
        ->and($data->addresses[0]->region)->toBe('VA')
        ->and($data->addresses[0]->postalCode)->toBe('23666')
        ->and($data->addresses[0]->country)->toBe('USA')
        ->and($data->addresses[0]->types)->toBe(['home']);
});

it('parses organization contact type and birthday', function () {
    $payload = contactCardPayload([
        'UID' => 'contact-4',
        'FN' => 'Analytical Engines',
        'ORG' => ['value' => ['Analytical Engines', 'R&D']],
        'BDAY' => '1815-12-10',
        'X-ABShowAs' => 'COMPANY',
    ]);

    $data = (new VCardParser)->parse($payload);

    expect($data->contactType)->toBe('organization')
        ->and($data->organization)->toBe('Analytical Engines')
        ->and($data->department)->toBe('R&D')
        ->and($data->birthday?->year)->toBe(1815)
        ->and($data->birthday?->month)->toBe(12)
        ->and($data->birthday?->day)->toBe(10);
});

it('captures unknown x-properties as extensions', function () {
    $payload = contactCardPayload([
        'UID' => 'contact-5',
        'FN' => 'Mystery Person',
        'X-CUSTOM-FIELD' => 'custom-value',
    ]);

    $data = (new VCardParser)->parse($payload);

    $names = array_map(fn ($extension) => $extension->name, $data->extensions);

    expect($names)->toContain('X-CUSTOM-FIELD');
});
