<?php

use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;
use Carbon\CarbonImmutable;

it('creates a calendar object from calendar object data', function (): void {
    $calendar = DavCalendar::factory()
        ->withInstance(['timezone' => 'Europe/Berlin'])
        ->create();
    $startsAt = CarbonImmutable::parse('2026-01-01 09:00:00', 'Europe/Berlin');
    $endsAt = CarbonImmutable::parse('2026-01-01 10:00:00', 'Europe/Berlin');
    $data = new CalendarObjectData(
        uri: '',
        raw: '',
        etag: '',
        size: 0,
        uid: 'event-1',
        summary: 'Planning',
        startsAt: $startsAt,
        endsAt: $endsAt,
        timezone: 'Europe/Berlin',
    );

    $object = $calendar->objects()->create(['uri' => 'planning.ics', 'data' => $data]);

    expect($object->calendar->is($calendar))->toBeTrue()
        ->and($object->uri)->toBe('planning.ics')
        ->and($object->uid)->toBe('event-1')
        ->and($object->component_type)->toBe('VEVENT')
        ->and($object->data->summary)->toBe('Planning')
        ->and($object->calendar_data)->toContain('SUMMARY:Planning');
});

it('updates a calendar object from calendar object data', function (): void {
    $object = DavCalendarObject::factory()->create();
    $data = new CalendarObjectData(
        uri: '',
        raw: '',
        etag: '',
        size: 0,
        uid: 'event-2',
        summary: 'Updated planning',
    );

    $object->update(['data' => $data]);

    expect($object->fresh())
        ->uid->toBe('event-2')
        ->component_type->toBe('VEVENT')
        ->data->summary->toBe('Updated planning')
        ->calendar_data->toContain('SUMMARY:Updated planning');
});

it('serializes calendar_data from structured fields when none is supplied', function (): void {
    $object = DavCalendarObject::factory()->create();

    expect($object->calendar_data)->toBeString()
        ->and($object->calendar_data)->not->toBe('')
        ->and($object->calendar_data)->toContain('BEGIN:VCALENDAR')
        ->and($object->calendar_data)->toContain('SUMMARY:'.$object->data->summary)
        ->and($object->etag)->toBe(sha1($object->calendar_data))
        ->and($object->size)->toBe(strlen($object->calendar_data));
});

it('preserves a supplied raw calendar_data verbatim and recomputes etag and size', function (): void {
    $raw = calendarObjectPayload('VEVENT', [
        'UID' => 'supplied-uid',
        'SUMMARY' => 'Supplied summary',
    ]);

    $object = DavCalendarObject::factory()->create([
        'calendar_data' => $raw,
    ]);

    expect($object->calendar_data)->toBe($raw)
        ->and($object->etag)->toBe(sha1($raw))
        ->and($object->size)->toBe(strlen($raw));
});

it('maps a model to CalendarObjectData', function (): void {
    $object = DavCalendarObject::factory()->create();

    $data = $object->toData();

    expect($data)->toBeInstanceOf(CalendarObjectData::class)
        ->and($data->summary)->toBe($object->data->summary)
        ->and($data->uid)->toBe($object->uid);
});

it('belongs to a calendar that owns many objects', function (): void {
    $calendar = DavCalendar::factory()->create();
    DavCalendarObject::factory()->count(3)->create(['dav_calendar_id' => $calendar->id]);

    expect($calendar->objects)->toHaveCount(3)
        ->and($calendar->objects->first()->calendar->id)->toBe($calendar->id);
});

it('resolves the owner relation to the stub user', function (): void {
    $calendar = DavCalendar::factory()->create();

    expect($calendar->owner)->not->toBeNull()
        ->and($calendar->owner)->toBeInstanceOf(OwnerUser::class);
});
