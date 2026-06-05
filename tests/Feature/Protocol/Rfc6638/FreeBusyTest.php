<?php

use Bambamboole\LaravelDav\Models\DavCalendar;

/**
 * @see https://www.rfc-editor.org/rfc/rfc6638.html#section-4.4
 */
it('[section 4.4] answers a scheduling outbox free-busy request with the recipient busy periods', function (): void {
    $organizer = davActor();
    $attendee = davActor();
    $aId = $organizer['owner']->getKey();
    $bId = $attendee['owner']->getKey();
    $aEmail = $organizer['owner']->getDavPrincipalEmail();
    $bEmail = $attendee['owner']->getDavPrincipalEmail();

    DavCalendar::factory()->create(['user_id' => $aId, 'uri' => 'personal']);
    DavCalendar::factory()->create(['user_id' => $bId, 'uri' => 'personal']);

    davPut($this, '/dav/calendars/'.$bId.'/personal/busy.ics', $attendee['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Tests//EN
        BEGIN:VEVENT
        UID:b-busy
        DTSTAMP:20260101T000000Z
        DTSTART:20260601T090000Z
        DTEND:20260601T100000Z
        SUMMARY:Busy
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    $response = $this->callDav('POST', '/dav/calendars/'.$aId.'/outbox/', $organizer['header'], ical(<<<ICS
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Tests//EN
        METHOD:REQUEST
        BEGIN:VFREEBUSY
        UID:fb-1
        DTSTAMP:20260101T000000Z
        DTSTART:20260601T000000Z
        DTEND:20260602T000000Z
        ORGANIZER:mailto:{$aEmail}
        ATTENDEE:mailto:{$bEmail}
        END:VFREEBUSY
        END:VCALENDAR
        ICS), [], 'text/calendar');

    expect($response->getStatusCode())->toBe(200);

    $body = $response->getContent();

    expect($body)->toContain('schedule-response')
        ->and($body)->toContain('mailto:'.$bEmail)
        ->and($body)->toContain('2.0;Success')
        ->and($body)->toContain('FREEBUSY:20260601T090000Z/20260601T100000Z');
});
