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
        ->and($data->isAllDay)->toBeFalse()
        ->and($data->isRecurring)->toBeFalse();
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

it('returns a raw-only dto for non-calendar payloads', function () {
    $payload = "BEGIN:VCARD\r\nVERSION:3.0\r\nEND:VCARD\r\n";

    $data = (new CalendarObjectParser)->parse($payload, 'weird.ics');

    expect($data->uri)->toBe('weird.ics')
        ->and($data->raw)->toBe($payload)
        ->and($data->etag)->toBe(sha1($payload))
        ->and($data->componentType)->toBeNull()
        ->and($data->summary)->toBeNull();
});

it('parses a VEVENT with TZID timezone on DTSTART and DTEND', function () {
    $payload = ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:event-tz
        SUMMARY:Berlin Meeting
        DTSTART;TZID=Europe/Berlin:20260603T090000
        DTEND;TZID=Europe/Berlin:20260603T100000
        END:VEVENT
        END:VCALENDAR
        ICS);

    $data = (new CalendarObjectParser)->parse($payload, 'event-tz.ics');

    expect($data->uid)->toBe('event-tz')
        ->and($data->componentType)->toBe('VEVENT')
        ->and($data->summary)->toBe('Berlin Meeting')
        ->and($data->timezone)->toBe('Europe/Berlin')
        ->and($data->isAllDay)->toBeFalse()
        ->and($data->startsAt?->toIso8601String())->toBe('2026-06-03T07:00:00+00:00')
        ->and($data->endsAt?->toIso8601String())->toBe('2026-06-03T08:00:00+00:00');
});

it('parses a VEVENT with STATUS, URL, and DESCRIPTION', function () {
    $payload = ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:event-rich
        SUMMARY:Team Retrospective
        DTSTART:20260610T140000Z
        DTEND:20260610T150000Z
        STATUS:TENTATIVE
        URL:https://meet.example.com/retro
        DESCRIPTION:Monthly team retrospective meeting
        END:VEVENT
        END:VCALENDAR
        ICS);

    $data = (new CalendarObjectParser)->parse($payload, 'event-rich.ics');

    expect($data->uid)->toBe('event-rich')
        ->and($data->summary)->toBe('Team Retrospective')
        ->and($data->status)->toBe('TENTATIVE')
        ->and($data->url)->toBe('https://meet.example.com/retro')
        ->and($data->description)->toBe('Monthly team retrospective meeting')
        ->and($data->raw)->toBe($payload);
});

it('parses a VEVENT with RRULE, preserving raw and extracting core fields', function () {
    $payload = ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:event-recurring
        SUMMARY:Weekly Standup
        DTSTART:20260603T090000Z
        DTEND:20260603T091500Z
        RRULE:FREQ=WEEKLY;BYDAY=MO,WE,FR
        END:VEVENT
        END:VCALENDAR
        ICS);

    $data = (new CalendarObjectParser)->parse($payload, 'event-recurring.ics');

    expect($data->uid)->toBe('event-recurring')
        ->and($data->componentType)->toBe('VEVENT')
        ->and($data->summary)->toBe('Weekly Standup')
        ->and($data->raw)->toBe($payload)
        ->and($data->isAllDay)->toBeFalse()
        ->and($data->isRecurring)->toBeTrue()
        ->and($data->startsAt?->toIso8601String())->toBe('2026-06-03T09:00:00+00:00');
});

it('parses a VJOURNAL and reports componentType', function () {
    $payload = ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VJOURNAL
        UID:journal-1
        SUMMARY:Daily Notes
        DTSTART:20260603T000000Z
        DESCRIPTION:Reflections on the day
        END:VJOURNAL
        END:VCALENDAR
        ICS);

    $data = (new CalendarObjectParser)->parse($payload, 'journal-1.ics');

    expect($data->uid)->toBe('journal-1')
        ->and($data->componentType)->toBe('VJOURNAL')
        ->and($data->summary)->toBe('Daily Notes')
        ->and($data->description)->toBe('Reflections on the day');
});

it('parses a multi-day all-day event with VALUE=DATE', function () {
    $payload = ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:event-multiday
        SUMMARY:Summer Vacation
        DTSTART;VALUE=DATE:20260720
        DTEND;VALUE=DATE:20260801
        END:VEVENT
        END:VCALENDAR
        ICS);

    $data = (new CalendarObjectParser)->parse($payload, 'event-multiday.ics');

    expect($data->uid)->toBe('event-multiday')
        ->and($data->componentType)->toBe('VEVENT')
        ->and($data->summary)->toBe('Summer Vacation')
        ->and($data->isAllDay)->toBeTrue()
        ->and($data->startsAt?->toDateString())->toBe('2026-07-20')
        ->and($data->endsAt?->toDateString())->toBe('2026-08-01');
});
