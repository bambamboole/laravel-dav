<?php

use Bambamboole\LaravelDav\Dto\Contact\ContactEmailAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactPhoneNumber;
use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Events\DavCollectionChanged;
use Bambamboole\LaravelDav\Exceptions\StaleDavResourceException;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCard;
use Bambamboole\LaravelDav\Models\DavChange;
use Illuminate\Support\Facades\Event;

it('creates a contact card and records a sync change', function (): void {
    $addressBook = DavAddressBook::factory()->create(['sync_token' => 1]);

    Event::fake([DavCollectionChanged::class]);

    $card = $addressBook->cards()->create(['data' => new ContactData(
        uri: '',
        raw: '',
        etag: '',
        size: 0,
        uid: 'contact-1',
        formattedName: 'Ada Lovelace',
        givenName: 'Ada',
        familyName: 'Lovelace',
        emailAddresses: [new ContactEmailAddress(['label' => 'work', 'value' => 'ada@example.com', 'types' => ['INTERNET', 'WORK']])],
        phoneNumbers: [new ContactPhoneNumber(['label' => 'mobile', 'value' => '+1 555 0100', 'types' => ['CELL']])],
    )]);

    expect($card)->toBeInstanceOf(DavCard::class)
        ->and($card->uri)->toBe('contact-1.vcf')
        ->and($card->data->formattedName)->toBe('Ada Lovelace')
        ->and($card->data->emailAddresses[0])->toBeInstanceOf(ContactEmailAddress::class)
        ->and($card->card_data)->toContain('FN:Ada Lovelace')
        ->and($card->card_data)->toContain('ada@example.com')
        ->and($addressBook->fresh()->sync_token)->toBe(2);

    expect(DavChange::query()->where('collection_type', 'address_book')->where('operation', 1)->count())->toBe(1);

    Event::assertDispatched(DavCollectionChanged::class, function (DavCollectionChanged $event) use ($addressBook, $card): bool {
        return $event->ownerId === (int) $addressBook->owner_id
            && $event->type === 'address_book'
            && $event->collectionId === $addressBook->getKey()
            && $event->resourceUri === $card->uri
            && $event->operation === 'added'
            && $event->syncToken === 2;
    });
});

it('updates a contact card with optimistic concurrency', function (): void {
    $card = DavCard::factory()->create([
        'data' => [
            'formattedName' => 'Old Name',
            'emailAddresses' => [['label' => 'work', 'value' => 'old@example.com', 'types' => ['INTERNET']]],
        ],
    ]);
    $etag = $card->etag;

    $card->expectingEtag($etag)->update(['data' => new ContactData(
        uri: $card->uri,
        raw: $card->card_data,
        etag: $card->etag,
        size: $card->size,
        uid: $card->data->uid,
        formattedName: 'New Name',
        emailAddresses: [new ContactEmailAddress(['label' => 'home', 'value' => 'new@example.com', 'types' => ['INTERNET', 'HOME']])],
    )]);

    expect($card->data->formattedName)->toBe('New Name')
        ->and($card->data->emailAddresses[0]->value)->toBe('new@example.com')
        ->and($card->card_data)->toContain('FN:New Name')
        ->and($card->card_data)->toContain('new@example.com')
        ->and($card->etag)->not->toBe($etag);
});

it('rejects stale contact card updates', function (): void {
    $card = DavCard::factory()->create();

    expect(fn () => $card->expectingEtag('stale')->update(['data' => $card->data]))
        ->toThrow(function (StaleDavResourceException $exception) use ($card): bool {
            return $exception->expectedEtag === 'stale'
                && $exception->actualEtag === $card->etag
                && $exception->resourceUri === $card->uri;
        });
});

it('deletes a contact card with optimistic concurrency', function (): void {
    $card = DavCard::factory()->create();
    $addressBook = $card->addressBook;

    $card->expectingEtag($card->etag)->delete();

    expect(DavCard::query()->whereKey($card->getKey())->exists())->toBeFalse()
        ->and($addressBook->fresh()->sync_token)->toBe(2)
        ->and(DavChange::query()->where('collection_type', 'address_book')->where('operation', 3)->count())->toBe(1);
});
