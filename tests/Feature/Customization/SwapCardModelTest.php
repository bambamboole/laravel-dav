<?php

use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavCard;
use Bambamboole\LaravelDav\Sabre\CardDav\AddressBookBackend;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;

class CustomCard extends DavCard
{
    public function isCustom(): bool
    {
        return true;
    }
}

beforeEach(function (): void {
    config(['dav.models.card' => CustomCard::class]);
});

it('resolves the swapped card model through the resolver', function (): void {
    expect(Dav::model('card'))->toBe(CustomCard::class);
});

it('drives the address book backend end-to-end with the swapped card model', function (): void {
    $owner = OwnerUser::factory()->create();
    $backend = app(AddressBookBackend::class);

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

    $card = CustomCard::query()->where('uri', 'card-1.vcf')->firstOrFail();
    expect($card)->toBeInstanceOf(CustomCard::class)
        ->and($card->isCustom())->toBeTrue()
        ->and($card->dav_address_book_id)->toBe($addressBookId)
        ->and($card->data->formattedName)->toBe('Ada Lovelace');

    $single = $backend->getCard($addressBookId, 'card-1.vcf');
    expect($single)->not->toBeFalse()
        ->and($single['carddata'])->toBe($payload);

    $cards = $backend->getCards($addressBookId);
    expect($cards)->toHaveCount(1)
        ->and($cards[0]['uri'])->toBe('card-1.vcf');
});
