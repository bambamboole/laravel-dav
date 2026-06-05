<?php

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Sabre\CalDav\CalendarBackend;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;

it('[section 5.2.3] advertises VJOURNAL in supported-calendar-component-set', function (): void {
    $owner = OwnerUser::factory()->create();
    $calendar = DavCalendar::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $backend = app(CalendarBackend::class);
    $calendars = $backend->getCalendarsForUser('principals/'.$owner->getKey());

    expect($calendars)->toHaveCount(1);

    $componentSet = $calendars[0]['{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set'];
    expect($componentSet->getValue())->toContain('VJOURNAL');
});

it('[section 4.1] saves and loads journals in a mixed calendar preserving raw iCalendar', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $eventPayload = ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:mixed-event-1
        DTSTAMP:20260603T000000Z
        SUMMARY:Deep Work
        DTSTART:20260603T070000Z
        DTEND:20260603T083000Z
        END:VEVENT
        END:VCALENDAR
        ICS);

    $todoPayload = ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VTODO
        UID:mixed-task-1
        DTSTAMP:20260603T000000Z
        SUMMARY:Follow up
        DUE:20260604T090000Z
        END:VTODO
        END:VCALENDAR
        ICS);

    $journalPayload = ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VJOURNAL
        UID:mixed-journal-1
        DTSTAMP:20260603T000000Z
        SUMMARY:Daily notes
        DTSTART:20260603T000000Z
        DESCRIPTION:Today was productive.
        END:VJOURNAL
        END:VCALENDAR
        ICS);

    $basePath = '/dav/calendars/'.$owner->getKey().'/personal/';

    davPut($this, $basePath.'mixed-event-1.ics', $actor['header'], $eventPayload, 'text/calendar')
        ->assertSuccessful();

    davPut($this, $basePath.'mixed-task-1.ics', $actor['header'], $todoPayload, 'text/calendar')
        ->assertSuccessful();

    davPut($this, $basePath.'mixed-journal-1.ics', $actor['header'], $journalPayload, 'text/calendar')
        ->assertStatus(201);

    $this->withHeaders(['Authorization' => $actor['header']])
        ->get($basePath.'mixed-event-1.ics')
        ->assertSuccessful()
        ->assertContent($eventPayload);

    $this->withHeaders(['Authorization' => $actor['header']])
        ->get($basePath.'mixed-task-1.ics')
        ->assertSuccessful()
        ->assertContent($todoPayload);

    $this->withHeaders(['Authorization' => $actor['header']])
        ->get($basePath.'mixed-journal-1.ics')
        ->assertSuccessful()
        ->assertContent($journalPayload);
});
