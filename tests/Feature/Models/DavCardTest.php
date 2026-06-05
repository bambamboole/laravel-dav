<?php

use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCard;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;

it('creates a card from contact data', function (): void {
    $addressBook = DavAddressBook::factory()->create();
    $data = ContactData::fromArray([
        'uid' => 'contact-1',
        'formattedName' => 'Ada Lovelace',
        'givenName' => 'Ada',
        'familyName' => 'Lovelace',
        'emailAddresses' => [['label' => 'work', 'value' => 'ada@example.com', 'types' => ['INTERNET', 'WORK']]],
        'phoneNumbers' => [['label' => 'mobile', 'value' => '+1 555 0100', 'types' => ['CELL'], 'isPreferred' => true]],
    ]);

    $card = $addressBook->cards()->create(['uri' => 'ada.vcf', 'data' => $data]);

    expect($card->addressBook->is($addressBook))->toBeTrue()
        ->and($card->uri)->toBe('ada.vcf')
        ->and($card->data->uid)->toBe('contact-1')
        ->and($card->data->formattedName)->toBe('Ada Lovelace')
        ->and($card->data->emailAddresses[0]->value)->toBe('ada@example.com')
        ->and($card->data->phoneNumbers[0]->value)->toBe('+1 555 0100')
        ->and($card->card_data)->toContain('FN:Ada Lovelace');
});

it('updates a card from contact data', function (): void {
    $card = DavCard::factory()->create();
    $data = ContactData::fromArray([
        'uid' => 'contact-2',
        'formattedName' => 'Grace Hopper',
        'emailAddresses' => [['label' => 'work', 'value' => 'grace@example.com', 'types' => ['INTERNET', 'WORK']]],
    ]);

    $card->update(['data' => $data]);
    $fresh = $card->fresh();

    expect($fresh->data->uid)->toBe('contact-2')
        ->and($fresh->data->formattedName)->toBe('Grace Hopper')
        ->and($fresh->data->emailAddresses[0]->value)->toBe('grace@example.com')
        ->and($fresh->card_data)->toContain('FN:Grace Hopper');
});

it('serializes card_data from structured fields when none is supplied', function (): void {
    $card = DavCard::factory()->create();

    expect($card->card_data)->toBeString()
        ->and($card->card_data)->not->toBe('')
        ->and($card->card_data)->toContain('BEGIN:VCARD')
        ->and($card->card_data)->toContain('FN:'.$card->data->formattedName)
        ->and($card->etag)->toBe(sha1($card->card_data))
        ->and($card->size)->toBe(strlen($card->card_data));
});

it('preserves a supplied raw card_data verbatim and recomputes etag and size', function (): void {
    $raw = contactCardPayload([
        'UID' => 'supplied-uid',
        'FN' => 'Supplied Name',
    ]);

    $card = DavCard::factory()->create([
        'card_data' => $raw,
    ]);

    expect($card->card_data)->toBe($raw)
        ->and($card->etag)->toBe(sha1($raw))
        ->and($card->size)->toBe(strlen($raw));
});

it('serializes phonetic and maiden-name data into card_data when none is supplied', function (): void {
    $card = DavCard::factory()->create([
        'card_data' => null,
        'data' => [
            'formattedName' => 'Ada Lovelace',
            'phoneticGivenName' => 'AY-dah',
            'phoneticMiddleName' => 'aw-GUS-tah',
            'phoneticFamilyName' => 'LUV-lays',
            'phoneticOrganization' => 'an-uh-LIT-ik-ul',
            'previousFamilyName' => 'Byron',
        ],
    ]);

    expect($card->card_data)->toContain('X-PHONETIC-FIRST-NAME:AY-dah')
        ->and($card->card_data)->toContain('X-PHONETIC-MIDDLE-NAME:aw-GUS-tah')
        ->and($card->card_data)->toContain('X-PHONETIC-LAST-NAME:LUV-lays')
        ->and($card->card_data)->toContain('X-PHONETIC-ORG:an-uh-LIT-ik-ul')
        ->and($card->card_data)->toContain('X-MAIDEN-NAME:Byron');
});

it('maps a model to ContactData', function (): void {
    $card = DavCard::factory()->create();

    $data = $card->toData();

    expect($data)->toBeInstanceOf(ContactData::class)
        ->and($data->formattedName)->toBe($card->data->formattedName)
        ->and($data->uid)->toBe($card->data->uid);
});

it('belongs to an address book that owns many cards', function (): void {
    $addressBook = DavAddressBook::factory()->create();
    DavCard::factory()->count(2)->create(['dav_address_book_id' => $addressBook->id]);

    expect($addressBook->cards)->toHaveCount(2)
        ->and($addressBook->cards->first()->addressBook->id)->toBe($addressBook->id);
});

it('resolves the owner relation to the stub user', function (): void {
    $addressBook = DavAddressBook::factory()->create();

    expect($addressBook->owner)->not->toBeNull()
        ->and($addressBook->owner)->toBeInstanceOf(OwnerUser::class);
});
