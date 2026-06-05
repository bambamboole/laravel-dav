<?php

use Bambamboole\LaravelDav\Sabre\CalDav\CalendarBackend;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;
use Illuminate\Support\Facades\DB;

/**
 * @return array<string, mixed>
 */
function veventTimeRangeFilter(string $start, string $end): array
{
    return [
        'name' => 'VCALENDAR',
        'comp-filters' => [[
            'name' => 'VEVENT',
            'is-not-defined' => false,
            'time-range' => [
                'start' => new DateTimeImmutable($start),
                'end' => new DateTimeImmutable($end),
            ],
            'comp-filters' => [],
            'prop-filters' => [],
        ]],
    ];
}

it('narrows the calendar-query candidate set in SQL using the denormalised columns', function (): void {
    $owner = OwnerUser::factory()->create();
    $backend = app(CalendarBackend::class);
    $calendarId = $backend->createCalendar('principals/'.$owner->getKey(), 'work', []);

    // Recurring weekly from before the window — recurs = true, must stay a candidate.
    $backend->createCalendarObject($calendarId, 'recurring.ics', calendarObjectPayload('VEVENT', [
        'UID' => 'r', 'DTSTART' => '20260105T090000Z', 'DTEND' => '20260105T093000Z', 'RRULE' => 'FREQ=WEEKLY;COUNT=20',
    ]));
    // Single event inside the window.
    $backend->createCalendarObject($calendarId, 'in-window.ics', calendarObjectPayload('VEVENT', [
        'UID' => 'i', 'DTSTART' => '20260209T100000Z', 'DTEND' => '20260209T110000Z',
    ]));
    // Single event far outside the window — must be excluded by SQL, never parsed.
    $backend->createCalendarObject($calendarId, 'out-of-window.ics', calendarObjectPayload('VEVENT', [
        'UID' => 'o', 'DTSTART' => '20250101T100000Z', 'DTEND' => '20250101T110000Z',
    ]));

    DB::enableQueryLog();
    $uris = $backend->calendarQuery($calendarId, veventTimeRangeFilter('2026-02-09T00:00:00Z', '2026-02-10T00:00:00Z'));
    $candidateQuery = collect(DB::getQueryLog())
        ->pluck('query')
        ->first(fn (string $sql): bool => str_contains($sql, 'from "dav_calendar_objects"') && str_contains($sql, 'recurs'));
    DB::disableQueryLog();

    expect($uris)->toEqualCanonicalizing(['recurring.ics', 'in-window.ics'])
        ->and($candidateQuery)->not->toBeNull()
        ->and($candidateQuery)->toContain('"component_type"');
});

it('filters out other component types in SQL', function (): void {
    $owner = OwnerUser::factory()->create();
    $backend = app(CalendarBackend::class);
    $calendarId = $backend->createCalendar('principals/'.$owner->getKey(), 'work', []);

    $backend->createCalendarObject($calendarId, 'event.ics', calendarObjectPayload('VEVENT', [
        'UID' => 'e', 'DTSTART' => '20260209T100000Z', 'DTEND' => '20260209T110000Z',
    ]));
    $backend->createCalendarObject($calendarId, 'task.ics', calendarObjectPayload('VTODO', [
        'UID' => 't', 'SUMMARY' => 'Task', 'DUE' => '20260209T120000Z',
    ]));

    $uris = $backend->calendarQuery($calendarId, veventTimeRangeFilter('2026-02-09T00:00:00Z', '2026-02-10T00:00:00Z'));

    expect($uris)->toBe(['event.ics']);
});
