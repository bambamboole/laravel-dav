<?php

use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCard;

it('reads contacts for an owner', function (): void {
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

    $contacts = Dav::repositories()->contacts($owner)->get();
    $foundById = Dav::repositories()->contacts($owner)->find($card->getKey());
    $foundByUri = Dav::repositories()->contacts($owner)->find('ada.vcf');
    $hidden = Dav::repositories()->contacts($owner)->find('grace.vcf');

    expect($contacts)->toHaveCount(1)
        ->and($contacts->first())->toBeInstanceOf(ContactData::class)
        ->and($contacts->first()->formattedName)->toBe('Ada Lovelace')
        ->and($foundById?->uri)->toBe('ada.vcf')
        ->and($foundByUri?->formattedName)->toBe('Ada Lovelace')
        ->and($hidden)->toBeNull();
});

it('reads and writes contacts through an address book repository', function (): void {
    $owner = config('dav.owner_model')::factory()->create();
    $addressBook = DavAddressBook::factory()->create([
        'user_id' => $owner->getKey(),
        'uri' => 'personal',
    ]);

    $contacts = Dav::repositories()
        ->addressBooks($owner)
        ->contacts('personal');

    $card = $contacts->create(ContactData::fromArray([
        'uid' => 'contact-1',
        'formattedName' => 'Alan Turing',
        'email' => 'alan@example.com',
    ]));
    $read = $contacts->find('contact-1.vcf');
    $updated = $contacts->update($card->getKey(), ContactData::fromArray([
        'uid' => 'contact-1',
        'formattedName' => 'Alan Mathison Turing',
        'email' => 'alan@example.com',
    ]), expectedEtag: $card->etag);

    $contacts->delete('contact-1.vcf', expectedEtag: $updated->etag);

    expect($read)->toBeInstanceOf(ContactData::class)
        ->and($read?->formattedName)->toBe('Alan Turing')
        ->and($updated->data->formattedName)->toBe('Alan Mathison Turing')
        ->and(DavCard::query()->whereKey($card->getKey())->exists())->toBeFalse();
});
