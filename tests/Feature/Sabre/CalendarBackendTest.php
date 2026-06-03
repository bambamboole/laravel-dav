<?php

use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Models\DavChange;
use Bambamboole\LaravelDav\Sabre\CalDav\CalendarBackend;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;

function calendarBackend(): CalendarBackend
{
    return app(CalendarBackend::class);
}

it('creates a calendar, persists an object, reads it back, and records a change', function (): void {
    $owner = OwnerUser::factory()->create();
    $backend = calendarBackend();

    $calendarId = $backend->createCalendar('principals/'.$owner->getKey(), 'work', [
        '{DAV:}displayname' => 'Work',
    ]);

    $payload = calendarObjectPayload('VEVENT', [
        'UID' => 'event-1',
        'SUMMARY' => 'Sprint planning',
        'DTSTART' => '20260101T090000Z',
        'DTEND' => '20260101T100000Z',
    ]);

    $etag = $backend->createCalendarObject($calendarId, 'event-1.ics', $payload);

    expect($etag)->toStartWith('"');

    $object = DavCalendarObject::query()->where('uri', 'event-1.ics')->firstOrFail();

    expect($object->calendar_data)->toBe($payload)
        ->and($object->etag)->toBe(sha1($payload))
        ->and($object->uid)->toBe('event-1');

    $objects = $backend->getCalendarObjects($calendarId);
    expect($objects)->toHaveCount(1)
        ->and($objects[0]['uri'])->toBe('event-1.ics');

    $single = $backend->getCalendarObject($calendarId, 'event-1.ics');
    expect($single)->not->toBeNull()
        ->and($single['calendardata'])->toBe($payload);

    expect(DavChange::query()->where('collection_type', 'calendar')->where('operation', 1)->count())->toBe(1);
});

it('deletes a calendar object and records the deletion', function (): void {
    $owner = OwnerUser::factory()->create();
    $backend = calendarBackend();

    $calendarId = $backend->createCalendar('principals/'.$owner->getKey(), 'work', []);
    $payload = calendarObjectPayload('VEVENT', ['UID' => 'event-1', 'SUMMARY' => 'Standup']);
    $backend->createCalendarObject($calendarId, 'event-1.ics', $payload);

    $backend->deleteCalendarObject($calendarId, 'event-1.ics');

    expect(DavCalendarObject::query()->where('uri', 'event-1.ics')->exists())->toBeFalse()
        ->and(DavChange::query()->where('collection_type', 'calendar')->where('operation', 3)->count())->toBe(1);
});
