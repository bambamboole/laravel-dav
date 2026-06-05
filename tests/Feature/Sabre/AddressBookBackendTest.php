<?php

use Bambamboole\LaravelDav\Models\DavCard;
use Bambamboole\LaravelDav\Models\DavChange;
use Bambamboole\LaravelDav\Sabre\CardDav\AddressBookBackend;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;

function addressBookBackend(): AddressBookBackend
{
    return app(AddressBookBackend::class);
}

it('creates an address book, persists a card, reads it back, and records a change', function (): void {
    $owner = OwnerUser::factory()->create();
    $backend = addressBookBackend();

    $addressBookId = $backend->createAddressBook('principals/'.$owner->getKey(), 'contacts', [
        '{DAV:}displayname' => 'Contacts',
    ]);

    $payload = contactCardPayload([
        'UID' => 'card-1',
        'FN' => 'Ada Lovelace',
        'N' => ['value' => 'Lovelace;Ada;;;'],
        'EMAIL' => 'ada@example.com',
    ]);

    $etag = $backend->createCard($addressBookId, 'card-1.vcf', $payload);

    expect($etag)->toStartWith('"');

    $card = DavCard::query()->where('uri', 'card-1.vcf')->firstOrFail();

    expect($card->card_data)->toBe($payload)
        ->and($card->etag)->toBe(sha1($payload))
        ->and($card->data->uid)->toBe('card-1')
        ->and($card->data->formattedName)->toBe('Ada Lovelace');

    $cards = $backend->getCards($addressBookId);
    expect($cards)->toHaveCount(1)
        ->and($cards[0]['uri'])->toBe('card-1.vcf');

    $single = $backend->getCard($addressBookId, 'card-1.vcf');
    expect($single)->not->toBeFalse()
        ->and($single['carddata'])->toBe($payload);

    expect(DavChange::query()->where('collection_type', 'address_book')->where('operation', 1)->count())->toBe(1);
});

it('deletes a card and records the deletion', function (): void {
    $owner = OwnerUser::factory()->create();
    $backend = addressBookBackend();

    $addressBookId = $backend->createAddressBook('principals/'.$owner->getKey(), 'contacts', []);
    $payload = contactCardPayload(['UID' => 'card-1', 'FN' => 'Ada Lovelace']);
    $backend->createCard($addressBookId, 'card-1.vcf', $payload);

    expect($backend->deleteCard($addressBookId, 'card-1.vcf'))->toBeTrue();

    expect(DavCard::query()->where('uri', 'card-1.vcf')->exists())->toBeFalse()
        ->and(DavChange::query()->where('collection_type', 'address_book')->where('operation', 3)->count())->toBe(1);
});
