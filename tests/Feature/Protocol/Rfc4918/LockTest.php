<?php

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Tests\TestCase;
use Illuminate\Support\Facades\DB;

function lockedCalendarObject(): array
{
    $actor = davActor();
    $owner = $actor['owner'];

    $calendar = DavCalendar::factory()->withInstance(['uri' => 'personal'])->create([
        'owner_id' => $owner->getKey(),
    ]);

    DavCalendarObject::factory()->for($calendar, 'calendar')->create([
        'uri' => 'event.ics',
    ]);

    return [
        'actor' => $actor,
        'path' => '/dav/calendars/'.$owner->getKey().'/personal/event.ics',
    ];
}

function lockCalendarObject(TestCase $test, string $path, string $authHeader): string
{
    $response = $test->callDav('LOCK', $path, $authHeader, <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:lockinfo xmlns:d="DAV:">
            <d:lockscope><d:exclusive /></d:lockscope>
            <d:locktype><d:write /></d:locktype>
            <d:owner><d:href>/principals/1/</d:href></d:owner>
        </d:lockinfo>
        XML, [
        'HTTP_DEPTH' => '0',
        'HTTP_TIMEOUT' => 'Second-600',
    ]);

    $response
        ->assertOk()
        ->assertHeader('Lock-Token');

    return (string) $response->headers->get('Lock-Token');
}

/**
 * @see https://www.rfc-editor.org/rfc/rfc4918.html#section-9.10
 */
it('[section 9.10] creates a persistent write lock and returns a lock token', function (): void {
    $fixture = lockedCalendarObject();

    $lockToken = lockCalendarObject($this, $fixture['path'], $fixture['actor']['header']);

    expect($lockToken)->toStartWith('<opaquelocktoken:')
        ->and(DB::table('dav_locks')->count())->toBe(1)
        ->and(DB::table('dav_locks')->value('uri'))->toBe(trim(substr($fixture['path'], strlen('/dav/')), '/'));
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4918.html#section-6
 * @see https://www.rfc-editor.org/rfc/rfc4918.html#section-9.7
 */
it('[sections 6 and 9.7] rejects writes without the matching lock token', function (): void {
    $fixture = lockedCalendarObject();
    $lockToken = lockCalendarObject($this, $fixture['path'], $fixture['actor']['header']);

    davPut($this, $fixture['path'], $fixture['actor']['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//LaravelDav//Tests//EN
        BEGIN:VEVENT
        UID:locked-event
        DTSTAMP:20260101T120000Z
        DTSTART:20260101T120000Z
        DTEND:20260101T130000Z
        SUMMARY:Locked update
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertStatus(423);

    $this->callDav('PUT', $fixture['path'], $fixture['actor']['header'], ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//LaravelDav//Tests//EN
        BEGIN:VEVENT
        UID:locked-event
        DTSTAMP:20260101T120000Z
        DTSTART:20260101T120000Z
        DTEND:20260101T130000Z
        SUMMARY:Unlocked update
        END:VEVENT
        END:VCALENDAR
        ICS), [
        'HTTP_IF' => '('.$lockToken.')',
    ], 'text/calendar')->assertSuccessful();
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4918.html#section-9.6
 */
it('[section 9.6] rejects deletes without the matching lock token', function (): void {
    $fixture = lockedCalendarObject();
    $lockToken = lockCalendarObject($this, $fixture['path'], $fixture['actor']['header']);

    $this->callDav('DELETE', $fixture['path'], $fixture['actor']['header'])
        ->assertStatus(423);

    $this->callDav('DELETE', $fixture['path'], $fixture['actor']['header'], server: [
        'HTTP_IF' => '('.$lockToken.')',
    ])->assertSuccessful();
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4918.html#section-9.11
 */
it('[section 9.11] removes a lock through UNLOCK', function (): void {
    $fixture = lockedCalendarObject();
    $lockToken = lockCalendarObject($this, $fixture['path'], $fixture['actor']['header']);

    $this->callDav('UNLOCK', $fixture['path'], $fixture['actor']['header'], server: [
        'HTTP_LOCK_TOKEN' => $lockToken,
    ])->assertNoContent();

    expect(DB::table('dav_locks')->count())->toBe(0);
});
