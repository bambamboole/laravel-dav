<?php

use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;

it('manages address books for an owner', function (): void {
    $owner = config('dav.owner_model')::factory()->create();
    $otherOwner = config('dav.owner_model')::factory()->create();
    DavAddressBook::factory()->create(['user_id' => $otherOwner->getKey(), 'uri' => 'private']);

    $created = DavAddressBook::create([
        'user_id' => $owner->getKey(),
        'uri' => 'personal',
        'display_name' => 'Personal Contacts',
        'description' => 'People',
    ]);
    $created->forceFill(['sync_token' => 7])->save();

    $updated = tap(DavAddressBook::forOwner($owner)->forKey('personal')->firstOrFail())
        ->update(['uri' => 'people', 'display_name' => 'People', 'description' => null]);

    DavAddressBook::forOwner($owner)->forKey('people')->firstOrFail()->delete();

    expect($created->display_name)->toBe('Personal Contacts')
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
        'description' => 'Team calendar',
        'color' => '#ff0000',
        'timezone' => 'UTC',
        'components' => ['VEVENT'],
    ]);
    $created->forceFill(['sync_token' => 7])->save();

    $updated = tap(DavCalendar::forOwner($owner)->forKey('work')->firstOrFail())
        ->update([
            'uri' => 'team',
            'display_name' => 'Team',
            'description' => null,
            'color' => '#00ff00',
            'timezone' => 'Europe/Berlin',
            'components' => ['VEVENT', 'VTODO'],
        ]);

    DavCalendar::forOwner($owner)->forKey('team')->firstOrFail()->delete();

    expect($created->display_name)->toBe('Work')
        ->and($updated->uri)->toBe('team')
        ->and($updated->components)->toBe(['VEVENT', 'VTODO'])
        ->and($updated->sync_token)->toBe(7)
        ->and(DavCalendar::forOwner($owner)->count())->toBe(0)
        ->and(DavCalendar::query()->where('uri', 'private')->exists())->toBeTrue();
});
