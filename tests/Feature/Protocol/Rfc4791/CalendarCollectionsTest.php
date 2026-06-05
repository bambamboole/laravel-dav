<?php

use Bambamboole\LaravelDav\Models\DavCalendar;
use Illuminate\Testing\TestResponse;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

function rfc4791CalendarTimezone(): string
{
    return ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VTIMEZONE
        TZID:Europe/Berlin
        BEGIN:STANDARD
        DTSTART:20261025T030000
        TZOFFSETFROM:+0200
        TZOFFSETTO:+0100
        TZNAME:CET
        END:STANDARD
        BEGIN:DAYLIGHT
        DTSTART:20260329T020000
        TZOFFSETFROM:+0100
        TZOFFSETTO:+0200
        TZNAME:CEST
        END:DAYLIGHT
        END:VTIMEZONE
        END:VCALENDAR
        ICS);
}

function rfc4791PropertyElement(TestResponse $response, string $namespace, string $localName): DOMElement
{
    $document = new DOMDocument;
    $document->loadXML($response->getContent());

    $xpath = new DOMXPath($document);
    $nodes = $xpath->query(sprintf('//*[namespace-uri()="%s" and local-name()="%s"]', $namespace, $localName));

    expect($nodes)->not->toBeFalse()
        ->and($nodes->length)->toBeGreaterThan(0)
        ->and($nodes->item(0))->toBeInstanceOf(DOMElement::class);

    return $nodes->item(0);
}

function rfc4791PropertyText(TestResponse $response, string $namespace, string $localName): string
{
    return trim(rfc4791PropertyElement($response, $namespace, $localName)->textContent);
}

/**
 * @return array<int, string>
 */
function rfc4791SupportedComponents(TestResponse $response): array
{
    $element = rfc4791PropertyElement($response, 'urn:ietf:params:xml:ns:caldav', 'supported-calendar-component-set');
    $xpath = new DOMXPath($element->ownerDocument);
    $nodes = $xpath->query('.//*[namespace-uri()="urn:ietf:params:xml:ns:caldav" and local-name()="comp"]', $element);

    expect($nodes)->not->toBeFalse();

    $components = [];

    foreach ($nodes as $node) {
        if ($node instanceof DOMElement) {
            $components[] = $node->getAttribute('name');
        }
    }

    sort($components);

    return $components;
}

function rfc4791TimezoneCalendar(TestResponse $response): VCalendar
{
    $calendar = Reader::read(rfc4791PropertyText($response, 'urn:ietf:params:xml:ns:caldav', 'calendar-timezone'));

    expect($calendar)->toBeInstanceOf(VCalendar::class);

    return $calendar;
}

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

    $response = $this->callDav('PROPFIND', '/dav/calendars/'.$owner->getKey().'/work/', $actor['header'], <<<'XML'
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
        ->assertStatus(207);

    expect(rfc4791PropertyText($response, 'DAV:', 'displayname'))->toBe('Work')
        ->and(rfc4791PropertyText($response, 'urn:ietf:params:xml:ns:caldav', 'calendar-description'))->toBe('Team calendar')
        ->and(rfc4791SupportedComponents($response))->toBe(['VEVENT']);
});

it('[sections 5.2.2 and 5.2.3] exposes calendar collection properties through PROPFIND', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];
    $timezone = rfc4791CalendarTimezone();

    DavCalendar::factory()->create([
        'user_id' => $owner->getKey(),
        'uri' => 'personal',
        'display_name' => 'Personal',
        'description' => 'Main calendar',
        'color' => '#3A87ADFF',
        'timezone' => $timezone,
        'components' => ['VEVENT', 'VTODO'],
        'sync_token' => 7,
    ]);

    $response = $this->callDav('PROPFIND', '/dav/calendars/'.$owner->getKey().'/personal/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav" xmlns:ical="http://apple.com/ns/ical/">
            <d:prop>
                <d:displayname />
                <cal:calendar-description />
                <ical:calendar-color />
                <cal:calendar-timezone />
                <cal:supported-calendar-component-set />
                <d:sync-token />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207);

    $timezoneCalendar = rfc4791TimezoneCalendar($response);

    try {
        $timezones = $timezoneCalendar->select('VTIMEZONE');

        expect(rfc4791PropertyText($response, 'DAV:', 'displayname'))->toBe('Personal')
            ->and(rfc4791PropertyText($response, 'urn:ietf:params:xml:ns:caldav', 'calendar-description'))->toBe('Main calendar')
            ->and(rfc4791PropertyText($response, 'http://apple.com/ns/ical/', 'calendar-color'))->toBe('#3A87ADFF')
            ->and(rfc4791SupportedComponents($response))->toBe(['VEVENT', 'VTODO'])
            ->and(rfc4791PropertyText($response, 'DAV:', 'sync-token'))->toBe('http://sabredav.org/ns/sync/7')
            ->and($timezones)->toHaveCount(1)
            ->and((string) $timezones[0]->TZID)->toBe('Europe/Berlin');
    } finally {
        $timezoneCalendar->destroy();
    }
});

it('[sections 5.2.2 and 5.2.3] updates calendar collection properties through PROPPATCH', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];
    $timezone = rfc4791CalendarTimezone();

    DavCalendar::factory()->create([
        'user_id' => $owner->getKey(),
        'uri' => 'personal',
        'display_name' => 'Personal',
        'description' => 'Main calendar',
        'color' => '#3A87ADFF',
    ]);

    $this->callDav('PROPPATCH', '/dav/calendars/'.$owner->getKey().'/personal/', $actor['header'], <<<XML
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propertyupdate xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav" xmlns:ical="http://apple.com/ns/ical/">
            <d:set>
                <d:prop>
                    <d:displayname>Work</d:displayname>
                    <cal:calendar-description>Team calendar</cal:calendar-description>
                    <ical:calendar-color>#CC0000FF</ical:calendar-color>
                    <cal:calendar-timezone>{$timezone}</cal:calendar-timezone>
                </d:prop>
            </d:set>
        </d:propertyupdate>
        XML)
        ->assertStatus(207);

    $calendar = DavCalendar::query()
        ->where('user_id', $owner->getKey())
        ->where('uri', 'personal')
        ->firstOrFail();

    expect($calendar)
        ->display_name->toBe('Work')
        ->description->toBe('Team calendar')
        ->color->toBe('#CC0000FF');

    expect(str_replace(["\r\n", "\r"], "\n", (string) $calendar->timezone))
        ->toBe(str_replace(["\r\n", "\r"], "\n", $timezone));

    $response = $this->callDav('PROPFIND', '/dav/calendars/'.$owner->getKey().'/personal/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav" xmlns:ical="http://apple.com/ns/ical/">
            <d:prop>
                <d:displayname />
                <cal:calendar-description />
                <ical:calendar-color />
                <cal:calendar-timezone />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207);

    $timezoneCalendar = rfc4791TimezoneCalendar($response);

    try {
        $timezones = $timezoneCalendar->select('VTIMEZONE');

        expect(rfc4791PropertyText($response, 'DAV:', 'displayname'))->toBe('Work')
            ->and(rfc4791PropertyText($response, 'urn:ietf:params:xml:ns:caldav', 'calendar-description'))->toBe('Team calendar')
            ->and(rfc4791PropertyText($response, 'http://apple.com/ns/ical/', 'calendar-color'))->toBe('#CC0000FF')
            ->and($timezones)->toHaveCount(1)
            ->and((string) $timezones[0]->TZID)->toBe('Europe/Berlin');
    } finally {
        $timezoneCalendar->destroy();
    }
});
