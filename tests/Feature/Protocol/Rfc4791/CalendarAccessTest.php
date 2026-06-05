<?php

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;

it('[sections 4.1 and 5.3.2] puts fetches lists and deletes a calendar object through CalDAV', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $payload = ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:event-1
        DTSTAMP:20260603T000000Z
        SUMMARY:Deep Work
        DTSTART:20260603T070000Z
        DTEND:20260603T083000Z
        END:VEVENT
        END:VCALENDAR
        ICS);

    $path = '/dav/calendars/'.$owner->getKey().'/personal/event-1.ics';

    davPut($this, $path, $actor['header'], $payload, 'text/calendar')->assertSuccessful();

    expect(DavCalendarObject::query()->where('uri', 'event-1.ics')->first())
        ->not->toBeNull()
        ->uid->toBe('event-1')
        ->calendar_data->toBe($payload);

    $this->withHeaders(['Authorization' => $actor['header']])
        ->get($path)
        ->assertSuccessful()
        ->assertContent($payload);

    $this->callDav('PROPFIND', '/dav/calendars/'.$owner->getKey().'/personal/', $actor['header'], server: [
        'HTTP_DEPTH' => '1',
    ])
        ->assertStatus(207)
        ->assertSee('event-1.ics', false);

    $this->withHeaders(['Authorization' => $actor['header']])
        ->delete($path)
        ->assertSuccessful();

    $this->withHeaders(['Authorization' => $actor['header']])
        ->get($path)
        ->assertNotFound();
});

it('[section 7.8] returns all calendar objects when calendar-query omits the component type filter', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    davPut(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/event-1.ics',
        $actor['header'],
        ical(<<<'ICS'
            BEGIN:VCALENDAR
            VERSION:2.0
            PRODID:-//Life OS//Tests//EN
            BEGIN:VEVENT
            UID:event-1
            DTSTAMP:20260603T000000Z
            SUMMARY:Project planning
            DTSTART:20260603T070000Z
            DTEND:20260603T080000Z
            END:VEVENT
            END:VCALENDAR
            ICS),
        'text/calendar',
    )->assertSuccessful();

    davPut(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/task-1.ics',
        $actor['header'],
        ical(<<<'ICS'
            BEGIN:VCALENDAR
            VERSION:2.0
            PRODID:-//Life OS//Tests//EN
            BEGIN:VTODO
            UID:task-1
            DTSTAMP:20260603T000000Z
            SUMMARY:Follow up
            DUE:20260604T090000Z
            END:VTODO
            END:VCALENDAR
            ICS),
        'text/calendar',
    )->assertSuccessful();

    davCalendarQueryReport(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/',
        $actor['header'],
        <<<'XML'
            <cal:filter>
                <cal:comp-filter name="VCALENDAR" />
            </cal:filter>
            XML,
    )
        ->assertStatus(207)
        ->assertSee('event-1.ics', false)
        ->assertSee('task-1.ics', false);
});

it('[section 7.8] returns matching calendar objects when a time range query omits the component type filter', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    davPut(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/event-1.ics',
        $actor['header'],
        ical(<<<'ICS'
            BEGIN:VCALENDAR
            VERSION:2.0
            PRODID:-//Life OS//Tests//EN
            BEGIN:VEVENT
            UID:event-1
            DTSTAMP:20260603T000000Z
            SUMMARY:Project planning
            DTSTART:20260603T070000Z
            DTEND:20260603T080000Z
            END:VEVENT
            END:VCALENDAR
            ICS),
        'text/calendar',
    )->assertSuccessful();

    davPut(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/task-1.ics',
        $actor['header'],
        ical(<<<'ICS'
            BEGIN:VCALENDAR
            VERSION:2.0
            PRODID:-//Life OS//Tests//EN
            BEGIN:VTODO
            UID:task-1
            DTSTAMP:20260603T000000Z
            SUMMARY:Follow up
            DTSTART:20260603T090000Z
            DUE:20260603T100000Z
            END:VTODO
            END:VCALENDAR
            ICS),
        'text/calendar',
    )->assertSuccessful();

    davPut(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/outside-range.ics',
        $actor['header'],
        ical(<<<'ICS'
            BEGIN:VCALENDAR
            VERSION:2.0
            PRODID:-//Life OS//Tests//EN
            BEGIN:VEVENT
            UID:outside-range
            DTSTAMP:20260603T000000Z
            SUMMARY:Later
            DTSTART:20260605T070000Z
            DTEND:20260605T080000Z
            END:VEVENT
            END:VCALENDAR
            ICS),
        'text/calendar',
    )->assertSuccessful();

    davCalendarQueryReport(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/',
        $actor['header'],
        <<<'XML'
            <cal:filter>
                <cal:comp-filter name="VCALENDAR">
                    <cal:time-range start="20260603T000000Z" end="20260604T000000Z" />
                </cal:comp-filter>
            </cal:filter>
            XML,
    )
        ->assertStatus(207)
        ->assertSee('event-1.ics', false)
        ->assertSee('task-1.ics', false)
        ->assertDontSee('outside-range.ics', false);
});

it('[section 7.8] combines calendar query filters as logical and', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    davPut(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/matching-event.ics',
        $actor['header'],
        ical(<<<'ICS'
            BEGIN:VCALENDAR
            VERSION:2.0
            PRODID:-//Life OS//Tests//EN
            BEGIN:VEVENT
            UID:matching-event
            DTSTAMP:20260603T000000Z
            SUMMARY:Project planning
            DTSTART:20260603T070000Z
            DTEND:20260603T080000Z
            END:VEVENT
            END:VCALENDAR
            ICS),
        'text/calendar',
    )->assertSuccessful();

    davPut(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/wrong-summary.ics',
        $actor['header'],
        ical(<<<'ICS'
            BEGIN:VCALENDAR
            VERSION:2.0
            PRODID:-//Life OS//Tests//EN
            BEGIN:VEVENT
            UID:wrong-summary
            DTSTAMP:20260603T000000Z
            SUMMARY:Personal appointment
            DTSTART:20260603T070000Z
            DTEND:20260603T080000Z
            END:VEVENT
            END:VCALENDAR
            ICS),
        'text/calendar',
    )->assertSuccessful();

    davPut(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/wrong-date.ics',
        $actor['header'],
        ical(<<<'ICS'
            BEGIN:VCALENDAR
            VERSION:2.0
            PRODID:-//Life OS//Tests//EN
            BEGIN:VEVENT
            UID:wrong-date
            DTSTAMP:20260603T000000Z
            SUMMARY:Project planning
            DTSTART:20260605T070000Z
            DTEND:20260605T080000Z
            END:VEVENT
            END:VCALENDAR
            ICS),
        'text/calendar',
    )->assertSuccessful();

    davCalendarQueryReport(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/',
        $actor['header'],
        <<<'XML'
            <cal:filter>
                <cal:comp-filter name="VCALENDAR">
                    <cal:comp-filter name="VEVENT">
                        <cal:time-range start="20260603T000000Z" end="20260604T000000Z" />
                        <cal:prop-filter name="SUMMARY">
                            <cal:text-match collation="i;ascii-casemap">Project</cal:text-match>
                        </cal:prop-filter>
                    </cal:comp-filter>
                </cal:comp-filter>
            </cal:filter>
            XML,
    )
        ->assertStatus(207)
        ->assertSee('matching-event.ics', false)
        ->assertDontSee('wrong-summary.ics', false)
        ->assertDontSee('wrong-date.ics', false);
});

it('[section 7.8] supports category text matching in calendar queries', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    davPut(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/hands-event.ics',
        $actor['header'],
        ical(<<<'ICS'
            BEGIN:VCALENDAR
            VERSION:2.0
            PRODID:-//Life OS//Tests//EN
            BEGIN:VEVENT
            UID:hands-event
            DTSTAMP:20260603T000000Z
            SUMMARY:Workshop
            CATEGORIES:hands,training
            DTSTART:20260603T070000Z
            DTEND:20260603T080000Z
            END:VEVENT
            END:VCALENDAR
            ICS),
        'text/calendar',
    )->assertSuccessful();

    davPut(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/finance-event.ics',
        $actor['header'],
        ical(<<<'ICS'
            BEGIN:VCALENDAR
            VERSION:2.0
            PRODID:-//Life OS//Tests//EN
            BEGIN:VEVENT
            UID:finance-event
            DTSTAMP:20260603T000000Z
            SUMMARY:Budget
            CATEGORIES:finance
            DTSTART:20260603T090000Z
            DTEND:20260603T100000Z
            END:VEVENT
            END:VCALENDAR
            ICS),
        'text/calendar',
    )->assertSuccessful();

    davCalendarQueryReport(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/',
        $actor['header'],
        <<<'XML'
            <cal:filter>
                <cal:comp-filter name="VCALENDAR">
                    <cal:comp-filter name="VEVENT">
                        <cal:prop-filter name="CATEGORIES">
                            <cal:text-match collation="i;octet">hands</cal:text-match>
                        </cal:prop-filter>
                    </cal:comp-filter>
                </cal:comp-filter>
            </cal:filter>
            XML,
    )
        ->assertStatus(207)
        ->assertSee('hands-event.ics', false)
        ->assertDontSee('finance-event.ics', false);
});
