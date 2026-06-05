<?php

use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Carbon\CarbonImmutable;

it('reads typed calendar objects for an owner', function (): void {
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

    $objects = Dav::repositories()->calendarObjects($owner)->get();
    $foundById = Dav::repositories()->calendarObjects($owner)->find($object->getKey());
    $foundByUri = Dav::repositories()->calendarObjects($owner)->find('planning.ics');
    $hidden = Dav::repositories()->calendarObjects($owner)->find('private.ics');

    expect($objects)->toHaveCount(1)
        ->and($objects->first())->toBeInstanceOf(CalendarObjectData::class)
        ->and($objects->first()->summary)->toBe('Planning')
        ->and($foundById?->uri)->toBe('planning.ics')
        ->and($foundByUri?->summary)->toBe('Planning')
        ->and($hidden)->toBeNull();
});

it('reads and writes calendar objects through a calendar repository', function (): void {
    $owner = config('dav.owner_model')::factory()->create();
    $calendar = DavCalendar::factory()->create([
        'user_id' => $owner->getKey(),
        'uri' => 'work',
    ]);

    $objects = Dav::repositories()
        ->calendars($owner)
        ->objects('work');

    $object = $objects->create(CalendarObjectData::fromArray([
        'uid' => 'event-1',
        'summary' => 'Standup',
        'startsAt' => '2026-01-01 09:00:00',
        'endsAt' => '2026-01-01 09:15:00',
        'timezone' => 'UTC',
    ]));
    $read = $objects->find('event-1.ics');
    $updated = $objects->update($object->getKey(), CalendarObjectData::fromArray([
        'uid' => 'event-1',
        'summary' => 'Team standup',
        'startsAt' => CarbonImmutable::parse('2026-01-01 09:00:00', 'UTC'),
        'endsAt' => CarbonImmutable::parse('2026-01-01 09:15:00', 'UTC'),
        'timezone' => 'UTC',
    ]), expectedEtag: $object->etag);

    $objects->delete('event-1.ics', expectedEtag: $updated->etag);

    expect($read)->toBeInstanceOf(CalendarObjectData::class)
        ->and($read?->summary)->toBe('Standup')
        ->and($updated->data->summary)->toBe('Team standup')
        ->and(DavCalendarObject::query()->whereKey($object->getKey())->exists())->toBeFalse();
});
