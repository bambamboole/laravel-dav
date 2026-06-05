<?php

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-4.1
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-5.3.2
 */
it('[sections 4.1 and 5.3.2] puts fetches lists and deletes a calendar object through CalDAV', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->withInstance(['uri' => 'personal'])->create(['owner_id' => $owner->getKey()]);

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

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-7.8
 */
it('[section 7.8] returns all calendar objects when calendar-query omits the component type filter', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->withInstance(['uri' => 'personal'])->create(['owner_id' => $owner->getKey()]);

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

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-7.8
 */
it('[section 7.8] returns matching calendar objects when a time range query omits the component type filter', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->withInstance(['uri' => 'personal'])->create(['owner_id' => $owner->getKey()]);

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

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-7.8
 */
it('[section 7.8] combines calendar query filters as logical and', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->withInstance(['uri' => 'personal'])->create(['owner_id' => $owner->getKey()]);

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

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-7.8
 */
it('[section 7.8] supports category text matching in calendar queries', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->withInstance(['uri' => 'personal'])->create(['owner_id' => $owner->getKey()]);

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

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-9.7.1
 */
it('[section 9.7.1] matches objects without a component through comp-filter is-not-defined', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->withInstance(['uri' => 'personal'])->create(['owner_id' => $owner->getKey()]);

    davPut($this, '/dav/calendars/'.$owner->getKey().'/personal/event-1.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:event-1
        DTSTAMP:20260603T000000Z
        SUMMARY:Planning
        DTSTART:20260603T070000Z
        DTEND:20260603T080000Z
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    davPut($this, '/dav/calendars/'.$owner->getKey().'/personal/task-1.ics', $actor['header'], ical(<<<'ICS'
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
        ICS), 'text/calendar')->assertSuccessful();

    davCalendarQueryReport($this, '/dav/calendars/'.$owner->getKey().'/personal/', $actor['header'], <<<'XML'
        <cal:filter>
            <cal:comp-filter name="VCALENDAR">
                <cal:comp-filter name="VEVENT">
                    <cal:is-not-defined />
                </cal:comp-filter>
            </cal:comp-filter>
        </cal:filter>
        XML)
        ->assertStatus(207)
        ->assertSee('task-1.ics', false)
        ->assertDontSee('event-1.ics', false);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-9.7.2
 */
it('[section 9.7.2] matches a missing property through prop-filter is-not-defined', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->withInstance(['uri' => 'personal'])->create(['owner_id' => $owner->getKey()]);

    davPut($this, '/dav/calendars/'.$owner->getKey().'/personal/with-location.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:with-location
        DTSTAMP:20260603T000000Z
        SUMMARY:Onsite
        LOCATION:Office
        DTSTART:20260603T070000Z
        DTEND:20260603T080000Z
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    davPut($this, '/dav/calendars/'.$owner->getKey().'/personal/no-location.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:no-location
        DTSTAMP:20260603T000000Z
        SUMMARY:Remote
        DTSTART:20260603T070000Z
        DTEND:20260603T080000Z
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    davCalendarQueryReport($this, '/dav/calendars/'.$owner->getKey().'/personal/', $actor['header'], <<<'XML'
        <cal:filter>
            <cal:comp-filter name="VCALENDAR">
                <cal:comp-filter name="VEVENT">
                    <cal:prop-filter name="LOCATION">
                        <cal:is-not-defined />
                    </cal:prop-filter>
                </cal:comp-filter>
            </cal:comp-filter>
        </cal:filter>
        XML)
        ->assertStatus(207)
        ->assertSee('no-location.ics', false)
        ->assertDontSee('with-location.ics', false);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-9.7.3
 */
it('[section 9.7.3] matches a property parameter through param-filter text-match', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->withInstance(['uri' => 'personal'])->create(['owner_id' => $owner->getKey()]);

    davPut($this, '/dav/calendars/'.$owner->getKey().'/personal/accepted.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:accepted
        DTSTAMP:20260603T000000Z
        SUMMARY:Sync
        DTSTART:20260603T070000Z
        DTEND:20260603T080000Z
        ATTENDEE;PARTSTAT=ACCEPTED:mailto:ada@example.com
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    davPut($this, '/dav/calendars/'.$owner->getKey().'/personal/declined.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:declined
        DTSTAMP:20260603T000000Z
        SUMMARY:Sync
        DTSTART:20260603T070000Z
        DTEND:20260603T080000Z
        ATTENDEE;PARTSTAT=DECLINED:mailto:grace@example.com
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    davCalendarQueryReport($this, '/dav/calendars/'.$owner->getKey().'/personal/', $actor['header'], <<<'XML'
        <cal:filter>
            <cal:comp-filter name="VCALENDAR">
                <cal:comp-filter name="VEVENT">
                    <cal:prop-filter name="ATTENDEE">
                        <cal:param-filter name="PARTSTAT">
                            <cal:text-match collation="i;ascii-casemap">ACCEPTED</cal:text-match>
                        </cal:param-filter>
                    </cal:prop-filter>
                </cal:comp-filter>
            </cal:comp-filter>
        </cal:filter>
        XML)
        ->assertStatus(207)
        ->assertSee('accepted.ics', false)
        ->assertDontSee('declined.ics', false);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-9.7.3
 */
it('[section 9.7.3] matches a missing parameter through param-filter is-not-defined', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->withInstance(['uri' => 'personal'])->create(['owner_id' => $owner->getKey()]);

    davPut($this, '/dav/calendars/'.$owner->getKey().'/personal/with-partstat.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:with-partstat
        DTSTAMP:20260603T000000Z
        SUMMARY:Sync
        DTSTART:20260603T070000Z
        DTEND:20260603T080000Z
        ATTENDEE;PARTSTAT=ACCEPTED:mailto:ada@example.com
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    davPut($this, '/dav/calendars/'.$owner->getKey().'/personal/no-partstat.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:no-partstat
        DTSTAMP:20260603T000000Z
        SUMMARY:Sync
        DTSTART:20260603T070000Z
        DTEND:20260603T080000Z
        ATTENDEE:mailto:grace@example.com
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    davCalendarQueryReport($this, '/dav/calendars/'.$owner->getKey().'/personal/', $actor['header'], <<<'XML'
        <cal:filter>
            <cal:comp-filter name="VCALENDAR">
                <cal:comp-filter name="VEVENT">
                    <cal:prop-filter name="ATTENDEE">
                        <cal:param-filter name="PARTSTAT">
                            <cal:is-not-defined />
                        </cal:param-filter>
                    </cal:prop-filter>
                </cal:comp-filter>
            </cal:comp-filter>
        </cal:filter>
        XML)
        ->assertStatus(207)
        ->assertSee('no-partstat.ics', false)
        ->assertDontSee('with-partstat.ics', false);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-9.7.5
 */
it('[section 9.7.5] negates a text-match condition', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->withInstance(['uri' => 'personal'])->create(['owner_id' => $owner->getKey()]);

    davPut($this, '/dav/calendars/'.$owner->getKey().'/personal/project.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:project
        DTSTAMP:20260603T000000Z
        SUMMARY:Project planning
        DTSTART:20260603T070000Z
        DTEND:20260603T080000Z
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    davPut($this, '/dav/calendars/'.$owner->getKey().'/personal/personal-time.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:personal-time
        DTSTAMP:20260603T000000Z
        SUMMARY:Lunch break
        DTSTART:20260603T110000Z
        DTEND:20260603T120000Z
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    davCalendarQueryReport($this, '/dav/calendars/'.$owner->getKey().'/personal/', $actor['header'], <<<'XML'
        <cal:filter>
            <cal:comp-filter name="VCALENDAR">
                <cal:comp-filter name="VEVENT">
                    <cal:prop-filter name="SUMMARY">
                        <cal:text-match negate-condition="yes" collation="i;ascii-casemap">Project</cal:text-match>
                    </cal:prop-filter>
                </cal:comp-filter>
            </cal:comp-filter>
        </cal:filter>
        XML)
        ->assertStatus(207)
        ->assertSee('personal-time.ics', false)
        ->assertDontSee('project.ics', false);
});
