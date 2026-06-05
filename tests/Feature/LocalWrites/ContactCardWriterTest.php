<?php

use Bambamboole\LaravelDav\Dto\Contact\ContactEmailAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactPhoneNumber;
use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Events\DavCollectionChanged;
use Bambamboole\LaravelDav\Exceptions\StaleDavResourceException;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCard;
use Bambamboole\LaravelDav\Models\DavChange;
use Illuminate\Support\Facades\Event;

it('creates a typed contact card and records a sync change', function (): void {
    $addressBook = DavAddressBook::factory()->create(['sync_token' => 1]);

    Event::fake([DavCollectionChanged::class]);

    $card = Dav::contacts()->create($addressBook, new ContactData(
        uri: '',
        raw: '',
        etag: '',
        size: 0,
        uid: 'contact-1',
        formattedName: 'Ada Lovelace',
        givenName: 'Ada',
        familyName: 'Lovelace',
        emails: [new ContactEmailAddress(['label' => 'work', 'value' => 'ada@example.com', 'types' => ['INTERNET', 'WORK']])],
        phones: [new ContactPhoneNumber(['label' => 'mobile', 'value' => '+1 555 0100', 'types' => ['CELL']])],
    ));

    expect($card)->toBeInstanceOf(DavCard::class)
        ->and($card->uri)->toBe('contact-1.vcf')
        ->and($card->full_name)->toBe('Ada Lovelace')
        ->and($card->email_addresses->first())->toBeInstanceOf(ContactEmailAddress::class)
        ->and($card->card_data)->toContain('FN:Ada Lovelace')
        ->and($card->card_data)->toContain('ada@example.com')
        ->and($addressBook->fresh()->sync_token)->toBe(2);

    expect(DavChange::query()->where('collection_type', 'address_book')->where('operation', 1)->count())->toBe(1);

    Event::assertDispatched(DavCollectionChanged::class, function (DavCollectionChanged $event) use ($addressBook, $card): bool {
        return $event->ownerId === (int) $addressBook->user_id
            && $event->type === 'address_book'
            && $event->collectionId === $addressBook->getKey()
            && $event->resourceUri === $card->uri
            && $event->operation === 'added'
            && $event->syncToken === 2;
    });
});

it('updates a typed contact card with optimistic concurrency', function (): void {
    $card = DavCard::factory()->create([
        'full_name' => 'Old Name',
        'emails' => ['old@example.com'],
        'email_addresses' => [['label' => 'work', 'value' => 'old@example.com', 'types' => ['INTERNET']]],
    ]);
    $etag = $card->etag;

    $updated = Dav::contacts()->update($card, new ContactData(
        uri: $card->uri,
        raw: $card->card_data,
        etag: $card->etag,
        size: $card->size,
        uid: $card->uid,
        formattedName: 'New Name',
        emails: [new ContactEmailAddress(['label' => 'home', 'value' => 'new@example.com', 'types' => ['INTERNET', 'HOME']])],
    ), expectedEtag: $etag);

    expect($updated->full_name)->toBe('New Name')
        ->and($updated->emails)->toBe(['new@example.com'])
        ->and($updated->card_data)->toContain('FN:New Name')
        ->and($updated->card_data)->toContain('new@example.com')
        ->and($updated->etag)->not->toBe($etag);
});

it('rejects stale contact card updates', function (): void {
    $card = DavCard::factory()->create();

    expect(fn () => Dav::contacts()->update($card, $card->toData(), expectedEtag: 'stale'))
        ->toThrow(function (StaleDavResourceException $exception) use ($card): bool {
            return $exception->expectedEtag === 'stale'
                && $exception->actualEtag === $card->etag
                && $exception->resourceUri === $card->uri;
        });
});

it('force updates a typed contact card without an expected etag', function (): void {
    $card = DavCard::factory()->create(['full_name' => 'Old Name']);

    $updated = Dav::contacts()->forceUpdate($card, ContactData::fromArray([
        'uid' => $card->uid,
        'formatted_name' => 'Forced Name',
    ]));

    expect($updated->full_name)->toBe('Forced Name');
});

it('deletes a typed contact card with optimistic concurrency', function (): void {
    $card = DavCard::factory()->create();
    $addressBook = $card->addressBook;

    Dav::contacts()->delete($card, expectedEtag: $card->etag);

    expect(DavCard::query()->whereKey($card->getKey())->exists())->toBeFalse()
        ->and($addressBook->fresh()->sync_token)->toBe(2)
        ->and(DavChange::query()->where('collection_type', 'address_book')->where('operation', 3)->count())->toBe(1);
});

it('force deletes a typed contact card without an expected etag', function (): void {
    $card = DavCard::factory()->create();

    Dav::contacts()->forceDelete($card);

    expect(DavCard::query()->whereKey($card->getKey())->exists())->toBeFalse();
});
