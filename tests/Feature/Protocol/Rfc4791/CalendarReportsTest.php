<?php

use Bambamboole\LaravelDav\Models\DavCalendar;

/**
 * @see https://www.rfc-editor.org/rfc/rfc4791.html#section-7.9
 */
it('[section 7.9] returns requested calendar objects through calendar-multiget', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->withInstance(['uri' => 'personal'])->create(['owner_id' => $owner->getKey()]);

    $eventPayload = ical(<<<'ICS'
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

    $todoPayload = ical(<<<'ICS'
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
        ICS);

    $basePath = '/dav/calendars/'.$owner->getKey().'/personal/';

    davPut($this, $basePath.'event-1.ics', $actor['header'], $eventPayload, 'text/calendar')
        ->assertSuccessful();

    davPut($this, $basePath.'task-1.ics', $actor['header'], $todoPayload, 'text/calendar')
        ->assertSuccessful();

    $this->callDav('REPORT', $basePath, $actor['header'], <<<XML
        <?xml version="1.0" encoding="utf-8" ?>
        <cal:calendar-multiget xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
            <d:prop>
                <d:getetag />
                <cal:calendar-data />
            </d:prop>
            <d:href>{$basePath}event-1.ics</d:href>
            <d:href>{$basePath}task-1.ics</d:href>
        </cal:calendar-multiget>
        XML, [
        'HTTP_DEPTH' => '1',
    ])
        ->assertStatus(207)
        ->assertSee('event-1.ics', false)
        ->assertSee('task-1.ics', false)
        ->assertSee('SUMMARY:Deep Work', false)
        ->assertSee('SUMMARY:Follow up', false);
});
