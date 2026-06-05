<?php

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Tests\TestCase;
use Illuminate\Testing\TestResponse;

function setInboxAvailability(TestCase $test, string $inboxPath, string $authHeader, string $availability): TestResponse
{
    return $test->callDav('PROPPATCH', $inboxPath, $authHeader, <<<XML
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propertyupdate xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
            <d:set>
                <d:prop>
                    <cal:calendar-availability>$availability</cal:calendar-availability>
                </d:prop>
            </d:set>
        </d:propertyupdate>
        XML);
}

function readInboxAvailability(TestCase $test, string $inboxPath, string $authHeader): TestResponse
{
    return $test->callDav('PROPFIND', $inboxPath, $authHeader, <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
            <d:prop>
                <cal:calendar-availability />
            </d:prop>
        </d:propfind>
        XML, ['HTTP_DEPTH' => '0']);
}

function workingHoursAvailability(): string
{
    return ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Tests//EN
        BEGIN:VAVAILABILITY
        UID:avail-1
        DTSTAMP:20260101T000000Z
        DTSTART:20260601T000000Z
        DTEND:20260602T000000Z
        BEGIN:AVAILABLE
        UID:avail-1-window
        DTSTAMP:20260101T000000Z
        DTSTART:20260601T090000Z
        DTEND:20260601T170000Z
        SUMMARY:Working hours
        END:AVAILABLE
        END:VAVAILABILITY
        END:VCALENDAR
        ICS);
}

/**
 * @see https://www.rfc-editor.org/rfc/rfc7953.html
 */
it('[section 5] stores and returns the calendar-availability property on the scheduling inbox', function (): void {
    $actor = davActor();
    $id = $actor['owner']->getKey();
    DavCalendar::factory()->withInstance(['uri' => 'personal'])->create(['owner_id' => $id]);

    setInboxAvailability($this, '/dav/calendars/'.$id.'/inbox/', $actor['header'], workingHoursAvailability())
        ->assertStatus(207);

    $body = readInboxAvailability($this, '/dav/calendars/'.$id.'/inbox/', $actor['header'])->getContent();

    expect($body)->toContain('BEGIN:VAVAILABILITY')
        ->and($body)->toContain('BEGIN:AVAILABLE')
        ->and($body)->toContain('DTSTART:20260601T090000Z');
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc7953.html#section-3
 */
it('[section 3] marks time outside the availability window as busy-unavailable in free-busy', function (): void {
    $organizer = davActor();
    $attendee = davActor();
    $aId = $organizer['owner']->getKey();
    $bId = $attendee['owner']->getKey();
    $aEmail = $organizer['owner']->getDavPrincipalEmail();
    $bEmail = $attendee['owner']->getDavPrincipalEmail();

    DavCalendar::factory()->withInstance(['uri' => 'personal'])->create(['owner_id' => $aId]);
    DavCalendar::factory()->withInstance(['uri' => 'personal'])->create(['owner_id' => $bId]);

    setInboxAvailability($this, '/dav/calendars/'.$bId.'/inbox/', $attendee['header'], workingHoursAvailability())
        ->assertStatus(207);

    $response = $this->callDav('POST', '/dav/calendars/'.$aId.'/outbox/', $organizer['header'], ical(<<<ICS
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Tests//EN
        METHOD:REQUEST
        BEGIN:VFREEBUSY
        UID:fb-avail
        DTSTAMP:20260101T000000Z
        DTSTART:20260601T000000Z
        DTEND:20260602T000000Z
        ORGANIZER:mailto:{$aEmail}
        ATTENDEE:mailto:{$bEmail}
        END:VFREEBUSY
        END:VCALENDAR
        ICS), [], 'text/calendar');

    $body = $response->getContent();

    expect($response->getStatusCode())->toBe(200)
        ->and($body)->toContain('2.0;Success')
        ->and($body)->toContain('FBTYPE=BUSY-UNAVAILABLE')
        ->and($body)->toContain('20260601T000000Z/20260601T090000Z')
        ->and($body)->toContain('20260601T170000Z/20260602T000000Z');
});
