<?php

use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarInstance;

it('scopes address book local writes to an owner', function (): void {
    $owner = (Dav::ownerModel())::factory()->create();
    $otherOwner = (Dav::ownerModel())::factory()->create();
    DavAddressBook::factory()->create(['owner_id' => $otherOwner->getKey(), 'uri' => 'private']);

    $addressBook = DavAddressBook::factory()->create([
        'owner_id' => $owner->getKey(),
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
    $owner = (Dav::ownerModel())::factory()->create();
    $otherOwner = (Dav::ownerModel())::factory()->create();
    DavCalendar::factory()->withInstance(['uri' => 'private'])->create(['owner_id' => $otherOwner->getKey()]);

    $created = DavCalendar::create([
        'owner_id' => $owner->getKey(),
    ]);
    $createdInstance = $created->instances()->create([
        'owner_id' => $owner->getKey(),
        'uri' => 'work',
        'access' => DavCalendarInstance::AccessOwner,
        'display_name' => 'Work',
    ]);
    $created->forceFill(['sync_token' => 7])->save();
    $initialUri = $createdInstance->uri;

    $updated = tap(DavCalendar::forOwner($owner)->forKey('work')->firstOrFail()->ownerInstance()->firstOrFail())
        ->update(['uri' => 'team']);
    $syncToken = $created->fresh()->sync_token;

    DavCalendar::forOwner($owner)->forKey('team')->firstOrFail()->delete();

    expect($initialUri)->toBe('work')
        ->and($updated->uri)->toBe('team')
        ->and($syncToken)->toBe(7)
        ->and(DavCalendar::forOwner($owner)->count())->toBe(0)
        ->and(DavCalendar::query()->whereHas('instances', fn ($query) => $query->where('uri', 'private'))->exists())->toBeTrue();
});
