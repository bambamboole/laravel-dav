<?php

use Bambamboole\LaravelDav\Sabre\CalDav\ExpandingVCalendar;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

function expandVtodo(string $raw, string $start, string $end): VCalendar
{
    $previous = VCalendar::$componentMap['VCALENDAR'];
    VCalendar::$componentMap['VCALENDAR'] = ExpandingVCalendar::class;

    try {
        $calendar = Reader::read(ical($raw));

        return $calendar->expand(new DateTimeImmutable($start), new DateTimeImmutable($end));
    } finally {
        VCalendar::$componentMap['VCALENDAR'] = $previous;
    }
}

it('shifts DUE by the master duration for generated VTODO instances', function (): void {
    $expanded = expandVtodo(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Tests//EN
        BEGIN:VTODO
        UID:task
        DTSTAMP:20000101T000000Z
        DTSTART:20000205T090000Z
        DUE:20000205T100000Z
        RRULE:FREQ=WEEKLY
        END:VTODO
        END:VCALENDAR
        ICS, '20000219T000000Z', '20000220T000000Z');

    $todos = $expanded->select('VTODO');

    expect($todos)->toHaveCount(1)
        ->and((string) $todos[0]->DTSTART)->toBe('20000219T090000Z')
        ->and((string) $todos[0]->DUE)->toBe('20000219T100000Z')
        ->and((string) $todos[0]->{'RECURRENCE-ID'})->toBe('20000219T090000Z')
        ->and(isset($todos[0]->RRULE))->toBeFalse();
});

it('preserves an overridden VTODO instance instead of shifting its DUE', function (): void {
    $expanded = expandVtodo(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Tests//EN
        BEGIN:VTODO
        UID:task
        DTSTAMP:20000101T000000Z
        DTSTART:20000205T090000Z
        DUE:20000205T100000Z
        RRULE:FREQ=WEEKLY
        END:VTODO
        BEGIN:VTODO
        UID:task
        DTSTAMP:20000101T000000Z
        RECURRENCE-ID:20000212T090000Z
        DTSTART:20000212T140000Z
        DUE:20000212T173000Z
        END:VTODO
        END:VCALENDAR
        ICS, '20000205T000000Z', '20000220T000000Z');

    $byRecurrence = [];
    foreach ($expanded->select('VTODO') as $todo) {
        $byRecurrence[(string) $todo->{'RECURRENCE-ID'}] = $todo;
    }

    expect($byRecurrence)->toHaveCount(3);

    expect((string) $byRecurrence['20000205T090000Z']->DUE)->toBe('20000205T100000Z');

    expect((string) $byRecurrence['20000212T090000Z']->DTSTART)->toBe('20000212T140000Z')
        ->and((string) $byRecurrence['20000212T090000Z']->DUE)->toBe('20000212T173000Z');

    expect((string) $byRecurrence['20000219T090000Z']->DUE)->toBe('20000219T100000Z');
});

it('still expands recurring VEVENTs exactly like the base implementation', function (): void {
    $expanded = expandVtodo(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Tests//EN
        BEGIN:VEVENT
        UID:event
        DTSTAMP:20000101T000000Z
        DTSTART:20000205T120000Z
        DTEND:20000205T130000Z
        RRULE:FREQ=WEEKLY
        END:VEVENT
        END:VCALENDAR
        ICS, '20000212T000000Z', '20000213T000000Z');

    $events = $expanded->select('VEVENT');

    expect($events)->toHaveCount(1)
        ->and((string) $events[0]->DTSTART)->toBe('20000212T120000Z')
        ->and((string) $events[0]->DTEND)->toBe('20000212T130000Z')
        ->and((string) $events[0]->{'RECURRENCE-ID'})->toBe('20000212T120000Z');
});
