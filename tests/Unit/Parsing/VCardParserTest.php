<?php

use Bambamboole\LaravelDav\Dto\Contact\ContactEmailAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactInstantMessage;
use Bambamboole\LaravelDav\Dto\Contact\ContactPhoneNumber;
use Bambamboole\LaravelDav\Dto\Contact\ContactPostalAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactSocialProfile;
use Bambamboole\LaravelDav\Dto\Contact\ContactUrl;
use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Parsing\VCardParser;

it('parses contact fields', function () {
    $payload = vcard(<<<'VCF'
        BEGIN:VCARD
        VERSION:3.0
        PRODID:-//Life OS//Tests//EN
        UID:contact-1
        FN:Ada Lovelace
        N:Lovelace;Ada;;;
        EMAIL;TYPE=work:ada@example.com
        TEL;TYPE=cell:+491234567
        ORG:Analytical Engines
        END:VCARD
        VCF);

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

it('parses a fully structured contact with all name parts and rich fields', function () {
    $payload = vcard(<<<'VCF'
        BEGIN:VCARD
        VERSION:3.0
        UID:contact-full
        FN:Ms. Ada Augusta Lovelace PhD
        N:Lovelace;Ada;Augusta;Ms.;PhD
        NICKNAME:Countess
        TITLE:Mathematician
        ROLE:Engineer
        ORG:Analytical Engines;Research
        NOTE:First programmer ever
        BDAY:1815-12-10
        ADR;TYPE=home;PREF:;;1 Engine St;London;;EC1;UK
        ADR;TYPE=work:;;2 Office Rd;Cambridge;;CB1;UK
        URL;TYPE=homepage:https://lovelace.example.com
        IMPP:xmpp:ada@jabber.example.com
        X-SKYPE:ada.lovelace
        X-SOCIALPROFILE;TYPE=twitter:https://twitter.com/adalovelace
        END:VCARD
        VCF);

    $data = (new VCardParser)->parse($payload, 'contact-full.vcf');

    expect($data->familyName)->toBe('Lovelace')
        ->and($data->givenName)->toBe('Ada')
        ->and($data->middleName)->toBe('Augusta')
        ->and($data->namePrefix)->toBe('Ms.')
        ->and($data->nameSuffix)->toBe('PhD')
        ->and($data->nickname)->toBe('Countess')
        ->and($data->jobTitle)->toBe('Mathematician')
        ->and($data->organization)->toBe('Analytical Engines')
        ->and($data->department)->toBe('Research')
        ->and($data->note)->toBe('First programmer ever')
        ->and($data->birthday?->year)->toBe(1815)
        ->and($data->birthday?->month)->toBe(12)
        ->and($data->birthday?->day)->toBe(10)
        ->and($data->addresses)->toHaveCount(2)
        ->and($data->addresses[0])->toBeInstanceOf(ContactPostalAddress::class)
        ->and($data->addresses[0]->street)->toBe('1 Engine St')
        ->and($data->addresses[0]->city)->toBe('London')
        ->and($data->addresses[0]->postalCode)->toBe('EC1')
        ->and($data->addresses[0]->country)->toBe('UK')
        ->and($data->addresses[0]->isPreferred)->toBeTrue()
        ->and($data->addresses[1]->street)->toBe('2 Office Rd')
        ->and($data->addresses[1]->city)->toBe('Cambridge')
        ->and($data->urls)->toHaveCount(1)
        ->and($data->urls[0])->toBeInstanceOf(ContactUrl::class)
        ->and($data->urls[0]->value)->toBe('https://lovelace.example.com')
        ->and($data->instantMessages)->toHaveCount(2)
        ->and($data->instantMessages[0])->toBeInstanceOf(ContactInstantMessage::class)
        ->and($data->instantMessages[0]->service)->toBe('xmpp')
        ->and($data->instantMessages[0]->username)->toBe('ada@jabber.example.com')
        ->and($data->instantMessages[1]->service)->toBe('skype')
        ->and($data->instantMessages[1]->username)->toBe('ada.lovelace')
        ->and($data->socialProfiles)->toHaveCount(1)
        ->and($data->socialProfiles[0])->toBeInstanceOf(ContactSocialProfile::class)
        ->and($data->socialProfiles[0]->service)->toBe('twitter')
        ->and($data->socialProfiles[0]->url)->toBe('https://twitter.com/adalovelace');
});

it('parses apple-style grouped properties with X-ABLABEL', function () {
    $payload = vcard(<<<'VCF'
        BEGIN:VCARD
        VERSION:3.0
        UID:contact-apple
        FN:Apple Test
        item1.EMAIL:apple@example.com
        item1.X-ABLABEL:_$!<Work>!$_
        item2.URL:https://apple.example.com
        item2.X-ABLABEL:Homepage
        END:VCARD
        VCF);

    $data = (new VCardParser)->parse($payload, 'contact-apple.vcf');

    expect($data->emails)->toHaveCount(1)
        ->and($data->emails[0]->value)->toBe('apple@example.com')
        ->and($data->emails[0]->label)->toBe('work')
        ->and($data->emails[0]->group)->toBe('ITEM1')
        ->and($data->urls)->toHaveCount(1)
        ->and($data->urls[0]->value)->toBe('https://apple.example.com')
        ->and($data->urls[0]->label)->toBe('Homepage')
        ->and($data->urls[0]->group)->toBe('ITEM2');
});

it('parses an apple organization contact with X-ABShowAs COMPANY', function () {
    $payload = vcard(<<<'VCF'
        BEGIN:VCARD
        VERSION:3.0
        UID:contact-org
        FN:ACME Corp
        ORG:ACME Corp;Engineering
        X-ABShowAs:COMPANY
        END:VCARD
        VCF);

    $data = (new VCardParser)->parse($payload, 'contact-org.vcf');

    expect($data->contactType)->toBe('organization')
        ->and($data->organization)->toBe('ACME Corp')
        ->and($data->department)->toBe('Engineering')
        ->and($data->formattedName)->toBe('ACME Corp');
});

it('parses a minimal contact with only FN and returns nulls for missing fields', function () {
    $payload = vcard(<<<'VCF'
        BEGIN:VCARD
        VERSION:3.0
        UID:contact-minimal
        FN:Just A Name
        END:VCARD
        VCF);

    $data = (new VCardParser)->parse($payload, 'contact-minimal.vcf');

    expect($data->raw)->toBe($payload)
        ->and($data->formattedName)->toBe('Just A Name')
        ->and($data->givenName)->toBeNull()
        ->and($data->familyName)->toBeNull()
        ->and($data->organization)->toBeNull()
        ->and($data->birthday)->toBeNull()
        ->and($data->emails)->toBe([])
        ->and($data->phones)->toBe([])
        ->and($data->addresses)->toBe([])
        ->and($data->urls)->toBe([])
        ->and($data->instantMessages)->toBe([])
        ->and($data->socialProfiles)->toBe([])
        ->and($data->contactType)->toBe('person');
});
