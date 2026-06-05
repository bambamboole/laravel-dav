<?php

use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;

it('scopes address book local writes to an owner', function (): void {
    $owner = config('dav.owner_model')::factory()->create();
    $otherOwner = config('dav.owner_model')::factory()->create();
    DavAddressBook::factory()->create(['user_id' => $otherOwner->getKey(), 'uri' => 'private']);

    $addressBook = DavAddressBook::factory()->create([
        'user_id' => $owner->getKey(),
        'uri' => 'personal',
        'sync_token' => 7,
    ]);

    $updated = tap(DavAddressBook::forOwner($owner)->forKey('personal')->firstOrFail())
        ->update(['uri' => 'people']);

    DavAddressBook::forOwner($owner)->forKey('people')->firstOrFail()->delete();

    expect($addressBook->uri)->toBe('personal')
        ->and($updated->uri)->toBe('people')
        ->and($updated->sync_token)->toBe(7)
        ->and(DavAddressBook::forOwner($owner)->count())->toBe(0)
        ->and(DavAddressBook::query()->where('uri', 'private')->exists())->toBeTrue();
});

it('manages calendars for an owner', function (): void {
    $owner = config('dav.owner_model')::factory()->create();
    $otherOwner = config('dav.owner_model')::factory()->create();
    DavCalendar::factory()->create(['user_id' => $otherOwner->getKey(), 'uri' => 'private']);

    $created = DavCalendar::create([
        'user_id' => $owner->getKey(),
        'uri' => 'work',
        'display_name' => 'Work',
    ]);
    $created->forceFill(['sync_token' => 7])->save();

    $updated = tap(DavCalendar::forOwner($owner)->forKey('work')->firstOrFail())
        ->update(['uri' => 'team']);

    DavCalendar::forOwner($owner)->forKey('team')->firstOrFail()->delete();

    expect($created->uri)->toBe('work')
        ->and($updated->uri)->toBe('team')
        ->and($updated->sync_token)->toBe(7)
        ->and(DavCalendar::forOwner($owner)->count())->toBe(0)
        ->and(DavCalendar::query()->where('uri', 'private')->exists())->toBeTrue();
});
