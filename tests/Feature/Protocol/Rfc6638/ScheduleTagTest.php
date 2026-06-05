<?php

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Illuminate\Testing\TestResponse;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

function rfc6638PropertyText(TestResponse $response, string $namespace, string $localName): string
{
    $document = new DOMDocument;
    $document->loadXML($response->getContent());

    $xpath = new DOMXPath($document);
    $nodes = $xpath->query(sprintf('//*[namespace-uri()="%s" and local-name()="%s"]', $namespace, $localName));

    expect($nodes)->not->toBeFalse()
        ->and($nodes->length)->toBeGreaterThan(0);

    return trim($nodes->item(0)->textContent);
}

function rfc6638CalendarObjectSummary(TestResponse $response): string
{
    $calendar = Reader::read($response->getContent());

    expect($calendar)->toBeInstanceOf(VCalendar::class);

    try {
        $events = $calendar->select('VEVENT');

        expect($events)->toHaveCount(1);

        return (string) $events[0]->SUMMARY;
    } finally {
        $calendar->destroy();
    }
}

function rfc6638CalendarObjectWithAttendeePartstat(TestResponse $response, string $attendeeEmail, string $partstat): string
{
    $calendar = Reader::read($response->getContent());

    expect($calendar)->toBeInstanceOf(VCalendar::class);

    try {
        $events = $calendar->select('VEVENT');

        expect($events)->toHaveCount(1);

        foreach ($events[0]->select('ATTENDEE') as $attendee) {
            if ($attendee->getValue() === 'mailto:'.$attendeeEmail) {
                $attendee['PARTSTAT'] = $partstat;

                return $calendar->serialize();
            }
        }

        expect()->fail('Expected attendee was not present in the calendar object.');
    } finally {
        $calendar->destroy();
    }
}

function rfc6638ScheduledEventPayload(string $organizerEmail, string $attendeeEmail, string $summary, string $attendeePartstat = 'NEEDS-ACTION'): string
{
    return ical(<<<ICS
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Tests//EN
        BEGIN:VEVENT
        UID:schedule-tag-meeting
        DTSTAMP:20260101T000000Z
        DTSTART:20260601T090000Z
        DTEND:20260601T100000Z
        ORGANIZER:mailto:{$organizerEmail}
        ATTENDEE;PARTSTAT={$attendeePartstat};RSVP=TRUE:mailto:{$attendeeEmail}
        SUMMARY:{$summary}
        END:VEVENT
        END:VCALENDAR
        ICS);
}

function rfc6638CalendarObjectPath(int|string $ownerId): string
{
    return '/dav/calendars/'.$ownerId.'/personal/schedule-tag-meeting.ics';
}

/**
 * @see https://www.rfc-editor.org/rfc/rfc6638.html#section-3.2.10
 * @see https://www.rfc-editor.org/rfc/rfc6638.html#section-8.2
 * @see https://www.rfc-editor.org/rfc/rfc6638.html#section-9.3
 */
it('[sections 3.2.10, 8.2 and 9.3] exposes schedule-tag on scheduling object resources', function (): void {
    $organizer = davActor();
    $attendee = davActor();
    $organizerId = $organizer['owner']->getKey();

    DavCalendar::factory()->create(['user_id' => $organizerId, 'uri' => 'personal']);

    $path = rfc6638CalendarObjectPath($organizerId);
    $payload = rfc6638ScheduledEventPayload(
        $organizer['owner']->getDavPrincipalEmail(),
        $attendee['owner']->getDavPrincipalEmail(),
        'Kickoff',
    );

    $putResponse = davPut($this, $path, $organizer['header'], $payload, 'text/calendar')
        ->assertSuccessful()
        ->assertHeader('Schedule-Tag');

    $scheduleTag = $putResponse->headers->get('Schedule-Tag');

    $this->withHeaders(['Authorization' => $organizer['header']])
        ->get($path)
        ->assertSuccessful()
        ->assertHeader('Schedule-Tag', $scheduleTag);

    $response = $this->callDav('PROPFIND', $path, $organizer['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
            <d:prop>
                <cal:schedule-tag />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207);

    expect(rfc6638PropertyText($response, 'urn:ietf:params:xml:ns:caldav', 'schedule-tag'))->toBe($scheduleTag);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc6638.html#section-3.2.10.1
 * @see https://www.rfc-editor.org/rfc/rfc6638.html#section-8.3
 */
it('[sections 3.2.10.1 and 8.3] enforces If-Schedule-Tag-Match on scheduling object PUT', function (): void {
    $organizer = davActor();
    $attendee = davActor();
    $organizerId = $organizer['owner']->getKey();

    DavCalendar::factory()->create(['user_id' => $organizerId, 'uri' => 'personal']);

    $path = rfc6638CalendarObjectPath($organizerId);
    $originalPayload = rfc6638ScheduledEventPayload(
        $organizer['owner']->getDavPrincipalEmail(),
        $attendee['owner']->getDavPrincipalEmail(),
        'Kickoff',
    );

    $scheduleTag = davPut($this, $path, $organizer['header'], $originalPayload, 'text/calendar')
        ->assertSuccessful()
        ->headers->get('Schedule-Tag');

    $updatedPayload = rfc6638ScheduledEventPayload(
        $organizer['owner']->getDavPrincipalEmail(),
        $attendee['owner']->getDavPrincipalEmail(),
        'Updated kickoff',
    );

    $this->callDav('PUT', $path, $organizer['header'], $updatedPayload, [
        'HTTP_IF_SCHEDULE_TAG_MATCH' => '"stale-schedule-tag"',
    ], 'text/calendar')
        ->assertStatus(412);

    $response = $this->withHeaders(['Authorization' => $organizer['header']])
        ->get($path)
        ->assertSuccessful();

    expect(rfc6638CalendarObjectSummary($response))->toBe('Kickoff');

    $acceptedResponse = $this->callDav('PUT', $path, $organizer['header'], $updatedPayload, [
        'HTTP_IF_SCHEDULE_TAG_MATCH' => $scheduleTag,
    ], 'text/calendar')
        ->assertSuccessful()
        ->assertHeader('Schedule-Tag');

    expect($acceptedResponse->headers->get('Schedule-Tag'))->not->toBe($scheduleTag);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc6638.html#section-3.2.10
 */
it('[section 3.2.10] changes schedule-tag for direct organizer PARTSTAT-only PUT updates', function (): void {
    $organizer = davActor();
    $attendee = davActor();
    $organizerId = $organizer['owner']->getKey();

    DavCalendar::factory()->create(['user_id' => $organizerId, 'uri' => 'personal']);

    $path = rfc6638CalendarObjectPath($organizerId);
    $payload = rfc6638ScheduledEventPayload(
        $organizer['owner']->getDavPrincipalEmail(),
        $attendee['owner']->getDavPrincipalEmail(),
        'Kickoff',
    );

    $scheduleTag = davPut($this, $path, $organizer['header'], $payload, 'text/calendar')
        ->assertSuccessful()
        ->headers->get('Schedule-Tag');

    $partstatOnlyPayload = rfc6638ScheduledEventPayload(
        $organizer['owner']->getDavPrincipalEmail(),
        $attendee['owner']->getDavPrincipalEmail(),
        'Kickoff',
        'ACCEPTED',
    );

    $this->callDav('PUT', $path, $organizer['header'], $partstatOnlyPayload, [
        'HTTP_IF_SCHEDULE_TAG_MATCH' => $scheduleTag,
    ], 'text/calendar')
        ->assertSuccessful()
        ->assertHeader('Schedule-Tag');

    expect($this->withHeaders(['Authorization' => $organizer['header']])
        ->get($path)
        ->assertSuccessful()
        ->headers->get('Schedule-Tag'))->not->toBe($scheduleTag);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc6638.html#section-3.2
 */
it('[section 3.2] keeps attendee schedule-tag stable for PARTSTAT-only auto-schedule updates', function (): void {
    $organizer = davActor();
    $attendee = davActor();
    $organizerId = $organizer['owner']->getKey();
    $attendeeId = $attendee['owner']->getKey();

    DavCalendar::factory()->create(['user_id' => $organizerId, 'uri' => 'personal']);
    DavCalendar::factory()->create(['user_id' => $attendeeId, 'uri' => 'personal']);

    $payload = rfc6638ScheduledEventPayload(
        $organizer['owner']->getDavPrincipalEmail(),
        $attendee['owner']->getDavPrincipalEmail(),
        'Kickoff',
    );

    davPut($this, rfc6638CalendarObjectPath($organizerId), $organizer['header'], $payload, 'text/calendar')
        ->assertSuccessful();

    $attendeeObject = DavCalendarObject::query()
        ->whereHas('calendar', fn ($query) => $query->where('user_id', $attendeeId)->where('uri', 'personal'))
        ->firstOrFail();
    $attendeePath = '/dav/calendars/'.$attendeeId.'/personal/'.$attendeeObject->uri;

    $response = $this->withHeaders(['Authorization' => $attendee['header']])
        ->get($attendeePath)
        ->assertSuccessful();
    $scheduleTag = $response->headers->get('Schedule-Tag');

    $partstatOnlyPayload = rfc6638CalendarObjectWithAttendeePartstat(
        $response,
        $attendee['owner']->getDavPrincipalEmail(),
        'ACCEPTED',
    );

    $this->callDav('PUT', $attendeePath, $attendee['header'], $partstatOnlyPayload, [
        'HTTP_IF_SCHEDULE_TAG_MATCH' => $scheduleTag,
    ], 'text/calendar')
        ->assertSuccessful()
        ->assertHeader('Schedule-Tag', $scheduleTag);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc6638.html#section-9.3
 */
it('[section 9.3] leaves non-scheduling calendar object resources without schedule-tag', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $path = '/dav/calendars/'.$owner->getKey().'/personal/private-note.ics';

    davPut($this, $path, $actor['header'], calendarObjectPayload('VEVENT', [
        'UID' => 'private-note',
        'DTSTAMP' => '20260101T000000Z',
        'DTSTART' => '20260601T090000Z',
        'DTEND' => '20260601T100000Z',
        'SUMMARY' => 'Private note',
    ]), 'text/calendar')
        ->assertSuccessful()
        ->assertHeaderMissing('Schedule-Tag');

    $this->withHeaders(['Authorization' => $actor['header']])
        ->get($path)
        ->assertSuccessful()
        ->assertHeaderMissing('Schedule-Tag');
});
