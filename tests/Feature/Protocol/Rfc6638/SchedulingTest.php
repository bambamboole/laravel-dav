<?php

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Sabre\CalDav\CalendarBackend;

function schedulingItip(string $uid): string
{
    return implode("\r\n", [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Tests//EN',
        'METHOD:REQUEST',
        'BEGIN:VEVENT',
        "UID:{$uid}",
        'DTSTAMP:20260101T000000Z',
        'DTSTART:20260601T090000Z',
        'DTEND:20260601T100000Z',
        'ORGANIZER:mailto:organizer@example.com',
        'ATTENDEE;PARTSTAT=NEEDS-ACTION:mailto:attendee@example.com',
        'SUMMARY:Project kickoff',
        'END:VEVENT',
        'END:VCALENDAR',
    ])."\r\n";
}

/**
 * @see https://www.rfc-editor.org/rfc/rfc6638.html#section-2.1
 */
it('[section 2.1] advertises the calendar-user-address-set and scheduling URLs on a principal', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];
    $id = $owner->getKey();

    $this->callDav('PROPFIND', '/dav/principals/'.$id.'/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
            <d:prop>
                <cal:calendar-user-address-set />
                <cal:schedule-inbox-URL />
                <cal:schedule-outbox-URL />
            </d:prop>
        </d:propfind>
        XML, ['HTTP_DEPTH' => '0'])
        ->assertStatus(207)
        ->assertSee('calendar-user-address-set', false)
        ->assertSee('mailto:'.$owner->getDavPrincipalEmail(), false)
        ->assertSee('/dav/principals/'.$id.'/', false)
        ->assertSee('/dav/calendars/'.$id.'/inbox/', false)
        ->assertSee('/dav/calendars/'.$id.'/outbox/', false);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc6638.html#section-2.2
 */
it('[section 2.2] provisions scheduling inbox and outbox collections in the calendar home', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];
    $id = $owner->getKey();

    DavCalendar::factory()->create(['user_id' => $id, 'uri' => 'personal']);

    $this->callDav('PROPFIND', '/dav/calendars/'.$id.'/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:resourcetype />
            </d:prop>
        </d:propfind>
        XML, ['HTTP_DEPTH' => '1'])
        ->assertStatus(207)
        ->assertSee('/dav/calendars/'.$id.'/inbox/', false)
        ->assertSee('/dav/calendars/'.$id.'/outbox/', false)
        ->assertSee('schedule-inbox', false)
        ->assertSee('schedule-outbox', false);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc6638.html#section-4
 */
it('[section 4] delivers an iTip message into a principal scheduling inbox', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];
    $id = $owner->getKey();

    app(CalendarBackend::class)->createSchedulingObject(
        'principals/'.$id,
        'invite-1.ics',
        schedulingItip('invite-1'),
    );

    $this->callDav('PROPFIND', '/dav/calendars/'.$id.'/inbox/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:getetag />
            </d:prop>
        </d:propfind>
        XML, ['HTTP_DEPTH' => '1'])
        ->assertStatus(207)
        ->assertSee('/dav/calendars/'.$id.'/inbox/invite-1.ics', false);

    $this->withHeaders(['Authorization' => $actor['header']])
        ->get('/dav/calendars/'.$id.'/inbox/invite-1.ics')
        ->assertSuccessful()
        ->assertSee('UID:invite-1', false);
});
