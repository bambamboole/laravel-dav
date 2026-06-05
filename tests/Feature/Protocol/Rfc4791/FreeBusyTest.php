<?php

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Tests\TestCase;
use Illuminate\Testing\TestResponse;

function freeBusyReport(TestCase $test, string $path, string $authHeader, string $start, string $end): TestResponse
{
    return $test->callDav('REPORT', $path, $authHeader, <<<XML
        <?xml version="1.0" encoding="utf-8" ?>
        <cal:free-busy-query xmlns:cal="urn:ietf:params:xml:ns:caldav">
            <cal:time-range start="{$start}" end="{$end}" />
        </cal:free-busy-query>
        XML, ['HTTP_DEPTH' => '1']);
}

it('[section 7.10] reports busy periods and ignores transparent events in a free-busy-query', function (): void {
    $actor = davActor();
    $id = $actor['owner']->getKey();
    DavCalendar::factory()->create(['user_id' => $id, 'uri' => 'personal']);

    davPut($this, '/dav/calendars/'.$id.'/personal/busy.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Tests//EN
        BEGIN:VEVENT
        UID:busy
        DTSTAMP:20260101T000000Z
        DTSTART:20260601T090000Z
        DTEND:20260601T100000Z
        SUMMARY:Busy
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    davPut($this, '/dav/calendars/'.$id.'/personal/free.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Tests//EN
        BEGIN:VEVENT
        UID:free
        DTSTAMP:20260101T000000Z
        DTSTART:20260601T140000Z
        DTEND:20260601T150000Z
        TRANSP:TRANSPARENT
        SUMMARY:Free
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    $response = freeBusyReport($this, '/dav/calendars/'.$id.'/personal/', $actor['header'], '20260601T000000Z', '20260602T000000Z');
    $body = $response->getContent();

    expect($response->getStatusCode())->toBe(200)
        ->and($body)->toContain('BEGIN:VFREEBUSY')
        ->and($body)->toContain('FREEBUSY:20260601T090000Z/20260601T100000Z')
        ->and($body)->not->toContain('20260601T140000Z');
});

it('[section 7.10] expands a recurring event into per-instance busy periods', function (): void {
    $actor = davActor();
    $id = $actor['owner']->getKey();
    DavCalendar::factory()->create(['user_id' => $id, 'uri' => 'personal']);

    davPut($this, '/dav/calendars/'.$id.'/personal/daily.ics', $actor['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Tests//EN
        BEGIN:VEVENT
        UID:daily
        DTSTAMP:20260101T000000Z
        DTSTART:20260601T090000Z
        DTEND:20260601T100000Z
        RRULE:FREQ=DAILY;COUNT=3
        SUMMARY:Standup
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    $body = freeBusyReport($this, '/dav/calendars/'.$id.'/personal/', $actor['header'], '20260601T000000Z', '20260604T000000Z')->getContent();

    expect($body)->toContain('20260601T090000Z/20260601T100000Z')
        ->and($body)->toContain('20260602T090000Z/20260602T100000Z')
        ->and($body)->toContain('20260603T090000Z/20260603T100000Z');
});
