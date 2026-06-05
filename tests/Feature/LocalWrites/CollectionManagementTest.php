<?php

use Bambamboole\LaravelDav\Dto\AddressBookData;
use Bambamboole\LaravelDav\Dto\CalendarData;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;

it('manages typed address books for an owner', function (): void {
    $owner = config('dav.owner_model')::factory()->create();
    $otherOwner = config('dav.owner_model')::factory()->create();
    DavAddressBook::factory()->create(['user_id' => $otherOwner->getKey(), 'uri' => 'private']);

    $addressBooks = Dav::addressBooks()->for($owner);
    $created = $addressBooks->create(new AddressBookData(
        uri: 'personal',
        displayName: 'Personal Contacts',
        description: 'People',
    ));
    $created->model->forceFill(['sync_token' => 7])->save();
    $updated = $addressBooks->update('personal', new AddressBookData(
        uri: 'people',
        displayName: 'People',
        description: null,
    ));

    $addressBooks->delete('people');

    expect($created->data()->displayName)->toBe('Personal Contacts')
        ->and($updated->data()->uri)->toBe('people')
        ->and($updated->data()->syncToken)->toBe(7)
        ->and($addressBooks->get())->toHaveCount(0)
        ->and(DavAddressBook::query()->where('uri', 'private')->exists())->toBeTrue();
});

it('manages typed calendars for an owner', function (): void {
    $owner = config('dav.owner_model')::factory()->create();
    $otherOwner = config('dav.owner_model')::factory()->create();
    DavCalendar::factory()->create(['user_id' => $otherOwner->getKey(), 'uri' => 'private']);

    $calendars = Dav::calendars()->for($owner);
    $created = $calendars->create(new CalendarData(
        uri: 'work',
        displayName: 'Work',
        description: 'Team calendar',
        color: '#ff0000',
        timezone: 'UTC',
        components: ['VEVENT'],
    ));
    $created->model->forceFill(['sync_token' => 7])->save();
    $updated = $calendars->update('work', new CalendarData(
        uri: 'team',
        displayName: 'Team',
        description: null,
        color: '#00ff00',
        timezone: 'Europe/Berlin',
        components: ['VEVENT', 'VTODO'],
    ));

    $calendars->delete('team');

    expect($created->data()->displayName)->toBe('Work')
        ->and($updated->data()->uri)->toBe('team')
        ->and($updated->data()->components)->toBe(['VEVENT', 'VTODO'])
        ->and($updated->data()->syncToken)->toBe(7)
        ->and($calendars->get())->toHaveCount(0)
        ->and(DavCalendar::query()->where('uri', 'private')->exists())->toBeTrue();
});
