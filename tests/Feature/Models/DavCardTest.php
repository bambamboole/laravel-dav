<?php

use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCard;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;

it('serializes card_data from structured fields when none is supplied', function (): void {
    $card = DavCard::factory()->create();

    expect($card->card_data)->toBeString()
        ->and($card->card_data)->not->toBe('')
        ->and($card->card_data)->toContain('BEGIN:VCARD')
        ->and($card->card_data)->toContain('FN:'.$card->full_name)
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

it('serializes phonetic and maiden-name columns into card_data when none is supplied', function (): void {
    $card = DavCard::factory()->create([
        'card_data' => null,
        'phonetic_given_name' => 'AY-dah',
        'phonetic_middle_name' => 'aw-GUS-tah',
        'phonetic_family_name' => 'LUV-lays',
        'phonetic_organization' => 'an-uh-LIT-ik-ul',
        'previous_family_name' => 'Byron',
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
        ->and($data->formattedName)->toBe($card->full_name)
        ->and($data->uid)->toBe($card->uid);
});

it('belongs to an address book that owns many cards', function (): void {
    $addressBook = DavAddressBook::factory()->create();
    DavCard::factory()->count(2)->create(['dav_address_book_id' => $addressBook->id]);

    expect($addressBook->cards)->toHaveCount(2)
        ->and($addressBook->cards->first()->addressBook->id)->toBe($addressBook->id);
});

it('resolves the owner relation to the stub user', function (): void {
    $addressBook = DavAddressBook::factory()->create();

    expect($addressBook->user)->not->toBeNull()
        ->and($addressBook->user)->toBeInstanceOf(OwnerUser::class);
});
