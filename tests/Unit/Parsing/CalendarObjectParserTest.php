<?php

use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Parsing\CalendarObjectParser;

it('parses event fields without discarding the raw payload', function () {
    $payload = calendarObjectPayload('VEVENT', [
        'UID' => 'event-1',
        'SUMMARY' => 'Deep Work',
        'LOCATION' => 'Office',
        'DTSTART' => '20260603T070000Z',
        'DTEND' => '20260603T083000Z',
    ]);

    $data = (new CalendarObjectParser)->parse($payload, 'event-1.ics');

    expect($data)->toBeInstanceOf(CalendarObjectData::class)
        ->and($data->uri)->toBe('event-1.ics')
        ->and($data->raw)->toBe($payload)
        ->and($data->etag)->toBe(sha1($payload))
        ->and($data->size)->toBe(strlen($payload))
        ->and($data->uid)->toBe('event-1')
        ->and($data->componentType)->toBe('VEVENT')
        ->and($data->summary)->toBe('Deep Work')
        ->and($data->location)->toBe('Office')
        ->and($data->isAllDay)->toBeFalse();
});

it('marks date-only events as all day', function () {
    $payload = calendarObjectPayload('VEVENT', [
        'UID' => 'event-2',
        'SUMMARY' => 'Conference',
        'DTSTART' => ['value' => '20260603', 'parameters' => ['VALUE' => 'DATE']],
        'DTEND' => ['value' => '20260604', 'parameters' => ['VALUE' => 'DATE']],
    ]);

    $data = (new CalendarObjectParser)->parse($payload);

    expect($data->uid)->toBe('event-2')
        ->and($data->componentType)->toBe('VEVENT')
        ->and($data->summary)->toBe('Conference')
        ->and($data->isAllDay)->toBeTrue()
        ->and($data->startsAt?->toDateString())->toBe('2026-06-03')
        ->and($data->endsAt?->toDateString())->toBe('2026-06-04');
});

it('parses todo fields with start and due dates', function () {
    $payload = calendarObjectPayload('VTODO', [
        'UID' => 'todo-1',
        'SUMMARY' => 'Prepare review',
        'LOCATION' => 'Desk',
        'DTSTART' => '20260603T070000Z',
        'DUE' => '20260603T083000Z',
    ]);

    $data = (new CalendarObjectParser)->parse($payload);

    expect($data->uid)->toBe('todo-1')
        ->and($data->componentType)->toBe('VTODO')
        ->and($data->summary)->toBe('Prepare review')
        ->and($data->location)->toBe('Desk')
        ->and($data->isAllDay)->toBeFalse()
        ->and($data->startsAt?->toIso8601String())->toBe('2026-06-03T07:00:00+00:00')
        ->and($data->endsAt?->toIso8601String())->toBe('2026-06-03T08:30:00+00:00');
});

it('parses due-only todos', function () {
    $payload = calendarObjectPayload('VTODO', [
        'UID' => 'todo-2',
        'SUMMARY' => 'Submit notes',
        'DUE' => '20260604T120000Z',
    ]);

    $data = (new CalendarObjectParser)->parse($payload);

    expect($data->uid)->toBe('todo-2')
        ->and($data->componentType)->toBe('VTODO')
        ->and($data->summary)->toBe('Submit notes')
        ->and($data->startsAt)->toBeNull()
        ->and($data->isAllDay)->toBeFalse()
        ->and($data->endsAt?->toIso8601String())->toBe('2026-06-04T12:00:00+00:00');
});

it('computes event end time from duration', function () {
    $payload = calendarObjectPayload('VEVENT', [
        'UID' => 'event-duration',
        'SUMMARY' => 'Workshop',
        'DTSTART' => '20260603T070000Z',
        'DURATION' => 'PT90M',
    ]);

    $data = (new CalendarObjectParser)->parse($payload);

    expect($data->startsAt?->toIso8601String())->toBe('2026-06-03T07:00:00+00:00')
        ->and($data->endsAt?->toIso8601String())->toBe('2026-06-03T08:30:00+00:00');
});

it('computes an implied one-day end for all-day events without dtend', function () {
    $payload = calendarObjectPayload('VEVENT', [
        'UID' => 'event-all-day',
        'SUMMARY' => 'Holiday',
        'DTSTART' => ['value' => '20260603', 'parameters' => ['VALUE' => 'DATE']],
    ]);

    $data = (new CalendarObjectParser)->parse($payload);

    expect($data->uid)->toBe('event-all-day')
        ->and($data->componentType)->toBe('VEVENT')
        ->and($data->isAllDay)->toBeTrue()
        ->and($data->startsAt?->toDateString())->toBe('2026-06-03')
        ->and($data->endsAt?->toDateString())->toBe('2026-06-04');
});

it('parses status and url', function () {
    $payload = calendarObjectPayload('VEVENT', [
        'UID' => 'e-status',
        'SUMMARY' => 'Trip',
        'DTSTART' => '20260603T070000Z',
        'DTEND' => '20260603T080000Z',
        'STATUS' => 'CONFIRMED',
        'URL' => 'https://example.com/e',
    ]);

    $data = (new CalendarObjectParser)->parse($payload);

    expect($data->status)->toBe('CONFIRMED')
        ->and($data->url)->toBe('https://example.com/e');
});

it('returns a raw-only dto for non-calendar payloads', function () {
    $payload = "BEGIN:VCARD\r\nVERSION:3.0\r\nEND:VCARD\r\n";

    $data = (new CalendarObjectParser)->parse($payload, 'weird.ics');

    expect($data->uri)->toBe('weird.ics')
        ->and($data->raw)->toBe($payload)
        ->and($data->etag)->toBe(sha1($payload))
        ->and($data->componentType)->toBeNull()
        ->and($data->summary)->toBeNull();
});
