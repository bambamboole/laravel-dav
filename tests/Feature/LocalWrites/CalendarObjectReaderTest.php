<?php

use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;

it('scopes calendar objects to their owner', function (): void {
    $owner = config('dav.owner_model')::factory()->create();
    $otherOwner = config('dav.owner_model')::factory()->create();
    $calendar = DavCalendar::factory()->create(['user_id' => $owner->getKey()]);
    $otherCalendar = DavCalendar::factory()->create(['user_id' => $otherOwner->getKey()]);
    $object = DavCalendarObject::factory()->create([
        'dav_calendar_id' => $calendar->getKey(),
        'uri' => 'planning.ics',
        'data' => ['summary' => 'Planning'],
    ]);
    DavCalendarObject::factory()->create([
        'dav_calendar_id' => $otherCalendar->getKey(),
        'uri' => 'private.ics',
        'data' => ['summary' => 'Private'],
    ]);

    $objects = DavCalendarObject::forOwner($owner)->get();
    $foundById = DavCalendarObject::forOwner($owner)->forKey($object->getKey())->first();
    $foundByUri = DavCalendarObject::forOwner($owner)->forKey('planning.ics')->first();
    $hidden = DavCalendarObject::forOwner($owner)->forKey('private.ics')->first();

    expect($objects)->toHaveCount(1)
        ->and($objects->first()->data)->toBeInstanceOf(CalendarObjectData::class)
        ->and($objects->first()->data->summary)->toBe('Planning')
        ->and($foundById?->uri)->toBe('planning.ics')
        ->and($foundByUri?->data->summary)->toBe('Planning')
        ->and($hidden)->toBeNull();
});

it('creates, reads, updates and deletes calendar objects through the relation', function (): void {
    $owner = config('dav.owner_model')::factory()->create();
    $calendar = DavCalendar::factory()->create(['user_id' => $owner->getKey()]);

    $object = $calendar->objects()->create(['data' => CalendarObjectData::fromArray([
        'uid' => 'event-1',
        'summary' => 'Standup',
        'startsAt' => '2026-01-01 09:00:00',
        'endsAt' => '2026-01-01 09:15:00',
        'timezone' => 'UTC',
    ])]);

    $read = DavCalendarObject::forOwner($owner)->forKey('event-1.ics')->first();

    $object->expectingEtag($object->etag)->update(['data' => CalendarObjectData::fromArray([
        'uid' => 'event-1',
        'summary' => 'Team standup',
        'startsAt' => '2026-01-01 09:00:00',
        'endsAt' => '2026-01-01 09:15:00',
        'timezone' => 'UTC',
    ])]);

    $object->expectingEtag($object->etag)->delete();

    expect($read?->data->summary)->toBe('Standup')
        ->and($object->data->summary)->toBe('Team standup')
        ->and(DavCalendarObject::query()->whereKey($object->getKey())->exists())->toBeFalse();
});
