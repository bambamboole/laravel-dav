<?php

use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCard;

it('scopes contacts to their owner', function (): void {
    $owner = config('dav.owner_model')::factory()->create();
    $otherOwner = config('dav.owner_model')::factory()->create();
    $addressBook = DavAddressBook::factory()->create(['user_id' => $owner->getKey()]);
    $otherAddressBook = DavAddressBook::factory()->create(['user_id' => $otherOwner->getKey()]);
    $card = DavCard::factory()->create([
        'dav_address_book_id' => $addressBook->getKey(),
        'uri' => 'ada.vcf',
        'data' => ['formattedName' => 'Ada Lovelace'],
    ]);
    DavCard::factory()->create([
        'dav_address_book_id' => $otherAddressBook->getKey(),
        'uri' => 'grace.vcf',
        'data' => ['formattedName' => 'Grace Hopper'],
    ]);

    $contacts = DavCard::forOwner($owner)->get();
    $foundById = DavCard::forOwner($owner)->forKey($card->getKey())->first();
    $foundByUri = DavCard::forOwner($owner)->forKey('ada.vcf')->first();
    $hidden = DavCard::forOwner($owner)->forKey('grace.vcf')->first();

    expect($contacts)->toHaveCount(1)
        ->and($contacts->first()->data)->toBeInstanceOf(ContactData::class)
        ->and($contacts->first()->data->formattedName)->toBe('Ada Lovelace')
        ->and($foundById?->uri)->toBe('ada.vcf')
        ->and($foundByUri?->data->formattedName)->toBe('Ada Lovelace')
        ->and($hidden)->toBeNull();
});

it('creates, reads, updates and deletes contacts through the relation', function (): void {
    $owner = config('dav.owner_model')::factory()->create();
    $addressBook = DavAddressBook::factory()->create(['user_id' => $owner->getKey()]);

    $card = $addressBook->cards()->create(['data' => ContactData::fromArray([
        'uid' => 'contact-1',
        'formattedName' => 'Alan Turing',
    ])]);

    $read = DavCard::forOwner($owner)->forKey('contact-1.vcf')->first();

    $card->expectingEtag($card->etag)->update(['data' => ContactData::fromArray([
        'uid' => 'contact-1',
        'formattedName' => 'Alan Mathison Turing',
    ])]);

    $card->expectingEtag($card->etag)->delete();

    expect($read?->data->formattedName)->toBe('Alan Turing')
        ->and($card->data->formattedName)->toBe('Alan Mathison Turing')
        ->and(DavCard::query()->whereKey($card->getKey())->exists())->toBeFalse();
});
