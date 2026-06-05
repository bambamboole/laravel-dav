<?php

use Bambamboole\LaravelDav\Models\DavCalendar;

it('[section 3.2] reports calendar changes through WebDAV sync', function (): void {
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

    davPut(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/event-1.ics',
        $actor['header'],
        $payload,
        'text/calendar',
    )->assertSuccessful();

    davSyncReport(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/',
        $actor['header'],
        'http://sabredav.org/ns/sync/1',
    )
        ->assertSuccessful()
        ->assertSee('event-1.ics', false)
        ->assertSee('sync-token', false)
        ->assertSee('http://sabredav.org/ns/sync/', false);
});
