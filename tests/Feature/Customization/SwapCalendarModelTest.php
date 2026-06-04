<?php

use Bambamboole\LaravelDav\LaravelDav;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Sabre\CalDav\CalendarBackend;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;

class CustomCalendar extends DavCalendar
{
    public function isCustom(): bool
    {
        return true;
    }
}

beforeEach(function (): void {
    config(['dav.models.calendar' => CustomCalendar::class]);
});

it('resolves the swapped calendar model through the resolver', function (): void {
    expect(LaravelDav::model('calendar'))->toBe(CustomCalendar::class);
});

it('drives the calendar backend end-to-end with the swapped model', function (): void {
    $owner = OwnerUser::factory()->create();
    $backend = app(CalendarBackend::class);

    $calendarId = $backend->createCalendar('principals/'.$owner->getKey(), 'work', [
        '{DAV:}displayname' => 'Work',
    ]);

    $calendar = CustomCalendar::query()->findOrFail($calendarId);
    expect($calendar)->toBeInstanceOf(CustomCalendar::class)
        ->and($calendar->isCustom())->toBeTrue();

    $payload = calendarObjectPayload('VEVENT', [
        'UID' => 'event-1',
        'SUMMARY' => 'Sprint planning',
        'DTSTART' => '20260101T090000Z',
        'DTEND' => '20260101T100000Z',
    ]);

    $etag = $backend->createCalendarObject($calendarId, 'event-1.ics', $payload);
    expect($etag)->toStartWith('"');

    $object = DavCalendarObject::query()->where('uri', 'event-1.ics')->firstOrFail();
    expect($object->dav_calendar_id)->toBe($calendar->id)
        ->and($object->calendar_data)->toBe($payload);

    expect($calendar->objects()->count())->toBe(1);

    $objects = $backend->getCalendarObjects($calendarId);
    expect($objects)->toHaveCount(1)
        ->and($objects[0]['uri'])->toBe('event-1.ics');

    $single = $backend->getCalendarObject($calendarId, 'event-1.ics');
    expect($single)->not->toBeNull()
        ->and($single['calendardata'])->toBe($payload);

    $changes = $backend->getChangesForCalendar($calendarId, '', 1);
    expect($changes['added'])->toContain('event-1.ics');

    $backend->deleteCalendarObject($calendarId, 'event-1.ics');
    expect(DavCalendarObject::query()->where('uri', 'event-1.ics')->exists())->toBeFalse();
});
