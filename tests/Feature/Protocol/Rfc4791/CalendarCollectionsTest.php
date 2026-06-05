<?php

use Bambamboole\LaravelDav\Models\DavCalendar;

it('[section 5.3.1] creates a calendar collection through MKCALENDAR', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    $this->callDav('MKCALENDAR', '/dav/calendars/'.$owner->getKey().'/work/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <cal:mkcalendar xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
            <d:set>
                <d:prop>
                    <d:displayname>Work</d:displayname>
                    <cal:calendar-description>Team calendar</cal:calendar-description>
                    <cal:supported-calendar-component-set>
                        <cal:comp name="VEVENT" />
                    </cal:supported-calendar-component-set>
                </d:prop>
            </d:set>
        </cal:mkcalendar>
        XML)
        ->assertCreated();

    $calendar = DavCalendar::query()
        ->where('user_id', $owner->getKey())
        ->where('uri', 'work')
        ->firstOrFail();

    expect($calendar)
        ->display_name->toBe('Work')
        ->description->toBe('Team calendar')
        ->components->toBe(['VEVENT']);

    $this->callDav('PROPFIND', '/dav/calendars/'.$owner->getKey().'/work/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
            <d:prop>
                <d:displayname />
                <cal:calendar-description />
                <cal:supported-calendar-component-set />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207)
        ->assertSee('Work', false)
        ->assertSee('Team calendar', false)
        ->assertSee('VEVENT', false);
});
