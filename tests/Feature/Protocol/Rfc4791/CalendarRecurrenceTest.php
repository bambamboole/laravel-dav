<?php

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCredential;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;
use Illuminate\Support\Facades\Hash;

/**
 * @return array{owner: OwnerUser, header: string, path: string}
 */
function recurrenceActor(): array
{
    $owner = OwnerUser::factory()->create();
    $secret = 'super-secret-token';
    $username = 'dav-'.$owner->getKey();

    DavCredential::factory()->create([
        'owner_id' => $owner->getKey(),
        'username' => $username,
        'secret_hash' => Hash::make($secret),
    ]);

    DavCalendar::factory()->withInstance(['uri' => 'personal'])->create(['owner_id' => $owner->getKey()]);

    return [
        'owner' => $owner,
        'header' => davAuthHeader($username, $secret),
        'path' => '/dav/calendars/'.$owner->getKey().'/personal/',
    ];
}

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-9.9
 */
it('[section 9.9] finds a later instance of a weekly recurring VEVENT', function (): void {
    $actor = recurrenceActor();

    davPut($this, $actor['path'].'weekly.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:weekly-event
        DTSTAMP:20260101T000000Z
        SUMMARY:Standup
        DTSTART:20260105T090000Z
        DTEND:20260105T093000Z
        RRULE:FREQ=WEEKLY;COUNT=20
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    davCalendarQueryReport($this, $actor['path'], $actor['header'], <<<'XML'
        <cal:filter>
            <cal:comp-filter name="VCALENDAR">
                <cal:comp-filter name="VEVENT">
                    <cal:time-range start="20260209T000000Z" end="20260210T000000Z" />
                </cal:comp-filter>
            </cal:comp-filter>
        </cal:filter>
        XML)
        ->assertStatus(207)
        ->assertSee('weekly.ics', false);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-9.9
 */
it('[section 9.9] does not match a recurring VEVENT outside every instance window', function (): void {
    $actor = recurrenceActor();

    davPut($this, $actor['path'].'weekly.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:weekly-event
        DTSTAMP:20260101T000000Z
        SUMMARY:Standup
        DTSTART:20260105T090000Z
        DTEND:20260105T093000Z
        RRULE:FREQ=WEEKLY;COUNT=4
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    davCalendarQueryReport($this, $actor['path'], $actor['header'], <<<'XML'
        <cal:filter>
            <cal:comp-filter name="VCALENDAR">
                <cal:comp-filter name="VEVENT">
                    <cal:time-range start="20260601T000000Z" end="20260602T000000Z" />
                </cal:comp-filter>
            </cal:comp-filter>
        </cal:filter>
        XML)
        ->assertStatus(207)
        ->assertDontSee('weekly.ics', false);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-9.9
 */
it('[section 9.9] finds a later instance of a recurring VTODO', function (): void {
    $actor = recurrenceActor();

    davPut($this, $actor['path'].'recurring-task.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VTODO
        UID:recurring-task
        DTSTAMP:20260101T000000Z
        SUMMARY:Pay rent
        DTSTART:20260101T080000Z
        DUE:20260101T090000Z
        RRULE:FREQ=MONTHLY;COUNT=12
        END:VTODO
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    davCalendarQueryReport($this, $actor['path'], $actor['header'], <<<'XML'
        <cal:filter>
            <cal:comp-filter name="VCALENDAR">
                <cal:comp-filter name="VTODO">
                    <cal:time-range start="20260401T000000Z" end="20260402T000000Z" />
                </cal:comp-filter>
            </cal:comp-filter>
        </cal:filter>
        XML)
        ->assertStatus(207)
        ->assertSee('recurring-task.ics', false);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-9.9
 */
it('[section 9.9] matches an infinite RRULE in a far future window', function (): void {
    $actor = recurrenceActor();

    davPut($this, $actor['path'].'infinite.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:infinite-event
        DTSTAMP:20000101T000000Z
        SUMMARY:Daily forever
        DTSTART:20000101T120000Z
        DTEND:20000101T130000Z
        RRULE:FREQ=DAILY
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    davCalendarQueryReport($this, $actor['path'], $actor['header'], <<<'XML'
        <cal:filter>
            <cal:comp-filter name="VCALENDAR">
                <cal:comp-filter name="VEVENT">
                    <cal:time-range start="20450101T000000Z" end="20450102T000000Z" />
                </cal:comp-filter>
            </cal:comp-filter>
        </cal:filter>
        XML)
        ->assertStatus(207)
        ->assertSee('infinite.ics', false);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-9.9
 */
it('[section 9.9] matches a recurrence overridden by a RECURRENCE-ID at its shifted time', function (): void {
    $actor = recurrenceActor();

    davPut($this, $actor['path'].'override.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:override-event
        DTSTAMP:20260101T000000Z
        SUMMARY:Weekly meeting
        DTSTART:20260105T090000Z
        DTEND:20260105T093000Z
        RRULE:FREQ=WEEKLY;COUNT=10
        END:VEVENT
        BEGIN:VEVENT
        UID:override-event
        DTSTAMP:20260101T000000Z
        RECURRENCE-ID:20260112T090000Z
        SUMMARY:Weekly meeting (moved)
        DTSTART:20260114T150000Z
        DTEND:20260114T153000Z
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    davCalendarQueryReport($this, $actor['path'], $actor['header'], <<<'XML'
        <cal:filter>
            <cal:comp-filter name="VCALENDAR">
                <cal:comp-filter name="VEVENT">
                    <cal:time-range start="20260114T140000Z" end="20260114T160000Z" />
                </cal:comp-filter>
            </cal:comp-filter>
        </cal:filter>
        XML)
        ->assertStatus(207)
        ->assertSee('override.ics', false);

    davCalendarQueryReport($this, $actor['path'], $actor['header'], <<<'XML'
        <cal:filter>
            <cal:comp-filter name="VCALENDAR">
                <cal:comp-filter name="VEVENT">
                    <cal:time-range start="20260112T080000Z" end="20260112T100000Z" />
                </cal:comp-filter>
            </cal:comp-filter>
        </cal:filter>
        XML)
        ->assertStatus(207)
        ->assertDontSee('override.ics', false);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-9.9
 */
it('[section 9.9] matches an all-day yearly recurrence in the following year', function (): void {
    $actor = recurrenceActor();

    davPut($this, $actor['path'].'birthday.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:birthday
        DTSTAMP:20260101T000000Z
        SUMMARY:Birthday
        DTSTART;VALUE=DATE:20260315
        DTEND;VALUE=DATE:20260316
        RRULE:FREQ=YEARLY
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    davCalendarQueryReport($this, $actor['path'], $actor['header'], <<<'XML'
        <cal:filter>
            <cal:comp-filter name="VCALENDAR">
                <cal:comp-filter name="VEVENT">
                    <cal:time-range start="20270315T000000Z" end="20270316T000000Z" />
                </cal:comp-filter>
            </cal:comp-filter>
        </cal:filter>
        XML)
        ->assertStatus(207)
        ->assertSee('birthday.ics', false);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-9.6.5
 */
it('[section 9.6.5] expands a recurring VEVENT into individual instances within the window', function (): void {
    $actor = recurrenceActor();

    davPut($this, $actor['path'].'expandable.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:expandable-event
        DTSTAMP:20000101T000000Z
        SUMMARY:Weekly sync
        DTSTART:20000205T120000Z
        DTEND:20000205T130000Z
        RRULE:FREQ=WEEKLY
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    $response = davCalendarQueryReport(
        $this,
        $actor['path'],
        $actor['header'],
        <<<'XML'
            <cal:filter>
                <cal:comp-filter name="VCALENDAR">
                    <cal:comp-filter name="VEVENT">
                        <cal:time-range start="20000212T000000Z" end="20000213T000000Z" />
                    </cal:comp-filter>
                </cal:comp-filter>
            </cal:filter>
            XML,
        '<cal:calendar-data><cal:expand start="20000212T000000Z" end="20000213T000000Z" /></cal:calendar-data>',
    );

    $response->assertStatus(207)
        ->assertSee('expandable.ics', false)
        ->assertSee('RECURRENCE-ID:20000212T120000Z', false)
        ->assertSee('DTSTART:20000212T120000Z', false)
        ->assertDontSee('RRULE', false);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-9.6.5
 */
it('[section 9.6.5] expands a recurring VTODO into individual instances within the window', function (): void {
    $actor = recurrenceActor();

    davPut($this, $actor['path'].'expandable-task.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VTODO
        UID:expandable-task
        DTSTAMP:20000101T000000Z
        SUMMARY:Water the plants
        DTSTART:20000205T090000Z
        DUE:20000205T170000Z
        RRULE:FREQ=WEEKLY
        END:VTODO
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    $response = davCalendarQueryReport(
        $this,
        $actor['path'],
        $actor['header'],
        <<<'XML'
            <cal:filter>
                <cal:comp-filter name="VCALENDAR">
                    <cal:comp-filter name="VTODO">
                        <cal:time-range start="20000212T000000Z" end="20000213T000000Z" />
                    </cal:comp-filter>
                </cal:comp-filter>
            </cal:filter>
            XML,
        '<cal:calendar-data><cal:expand start="20000212T000000Z" end="20000213T000000Z" /></cal:calendar-data>',
    );

    $response->assertStatus(207)
        ->assertSee('expandable-task.ics', false)
        ->assertSee('RECURRENCE-ID:20000212T090000Z', false)
        ->assertSee('DTSTART:20000212T090000Z', false)
        ->assertSee('DUE:20000212T170000Z', false)
        ->assertDontSee('RRULE', false);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-9.6.5
 */
it('[section 9.6.5] expands a recurring VJOURNAL into individual instances within the window', function (): void {
    $actor = recurrenceActor();

    davPut($this, $actor['path'].'expandable-journal.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VJOURNAL
        UID:expandable-journal
        DTSTAMP:20000101T000000Z
        SUMMARY:Daily log
        DTSTART:20000205T080000Z
        RRULE:FREQ=WEEKLY
        END:VJOURNAL
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    $response = davCalendarQueryReport(
        $this,
        $actor['path'],
        $actor['header'],
        <<<'XML'
            <cal:filter>
                <cal:comp-filter name="VCALENDAR">
                    <cal:comp-filter name="VJOURNAL">
                        <cal:time-range start="20000212T000000Z" end="20000213T000000Z" />
                    </cal:comp-filter>
                </cal:comp-filter>
            </cal:filter>
            XML,
        '<cal:calendar-data><cal:expand start="20000212T000000Z" end="20000213T000000Z" /></cal:calendar-data>',
    );

    $response->assertStatus(207)
        ->assertSee('expandable-journal.ics', false)
        ->assertSee('RECURRENCE-ID:20000212T080000Z', false)
        ->assertSee('DTSTART:20000212T080000Z', false)
        ->assertDontSee('RRULE', false);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-7.9
 */
it('[section 7.9] expands a recurring VTODO requested through calendar-multiget', function (): void {
    $actor = recurrenceActor();
    $href = $actor['path'].'multiget-task.ics';

    davPut($this, $href, $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VTODO
        UID:multiget-task
        DTSTAMP:20000101T000000Z
        SUMMARY:Weekly review
        DTSTART:20000205T090000Z
        DUE:20000205T100000Z
        RRULE:FREQ=WEEKLY
        END:VTODO
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    $response = $this->callDav('REPORT', $actor['path'], $actor['header'], <<<XML
        <?xml version="1.0" encoding="utf-8" ?>
        <cal:calendar-multiget xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
            <d:prop>
                <d:getetag />
                <cal:calendar-data><cal:expand start="20000212T000000Z" end="20000213T000000Z" /></cal:calendar-data>
            </d:prop>
            <d:href>{$href}</d:href>
        </cal:calendar-multiget>
        XML, ['HTTP_DEPTH' => '1']);

    $response->assertStatus(207)
        ->assertSee('multiget-task.ics', false)
        ->assertSee('RECURRENCE-ID:20000212T090000Z', false)
        ->assertSee('DTSTART:20000212T090000Z', false)
        ->assertSee('DUE:20000212T100000Z', false)
        ->assertDontSee('RRULE', false);
});
