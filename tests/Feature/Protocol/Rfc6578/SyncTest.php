<?php

use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Illuminate\Testing\TestResponse;

function rfc6578SyncToken(TestResponse $response): string
{
    $document = rfc6578MultistatusDocument($response);
    $xpath = new DOMXPath($document);
    $nodes = $xpath->query('/*[namespace-uri()="DAV:" and local-name()="multistatus"]/*[namespace-uri()="DAV:" and local-name()="sync-token"]');

    expect($nodes)->not->toBeFalse()
        ->and($nodes->length)->toBe(1);

    return trim($nodes->item(0)->textContent);
}

/**
 * @return array<string, int>
 */
function rfc6578ResponseStatuses(TestResponse $response): array
{
    $document = rfc6578MultistatusDocument($response);
    $xpath = new DOMXPath($document);
    $responseElements = $xpath->query('/*[namespace-uri()="DAV:" and local-name()="multistatus"]/*[namespace-uri()="DAV:" and local-name()="response"]');

    expect($responseElements)->not->toBeFalse();

    $statuses = [];

    foreach ($responseElements as $responseElement) {
        if (! $responseElement instanceof DOMElement) {
            continue;
        }

        $href = $xpath->query('./*[namespace-uri()="DAV:" and local-name()="href"]', $responseElement);
        $status = $xpath->query('.//*[namespace-uri()="DAV:" and local-name()="status"]', $responseElement);

        expect($href)->not->toBeFalse()
            ->and($href->length)->toBe(1)
            ->and($status)->not->toBeFalse()
            ->and($status->length)->toBeGreaterThan(0);

        preg_match('/HTTP\/1\.1\s+(\d{3})\s+/', trim($status->item(0)->textContent), $matches);

        expect($matches)->toHaveKey(1);

        $statuses[trim($href->item(0)->textContent)] = (int) $matches[1];
    }

    ksort($statuses);

    return $statuses;
}

function rfc6578MultistatusDocument(TestResponse $response): DOMDocument
{
    $document = new DOMDocument;

    expect($document->loadXML($response->getContent()))->toBeTrue();

    return $document;
}

/**
 * @see https://www.rfc-editor.org/rfc/rfc6578.html#section-3.2
 */
it('[section 3.2] reports calendar changes through WebDAV sync', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $basePath = '/dav/calendars/'.$owner->getKey().'/personal/';

    davPut($this, $basePath.'event-1.ics', $actor['header'], calendarObjectPayload('VEVENT', [
        'UID' => 'event-1',
        'DTSTAMP' => '20260603T000000Z',
        'SUMMARY' => 'Deep Work',
        'DTSTART' => '20260603T070000Z',
        'DTEND' => '20260603T083000Z',
    ]), 'text/calendar')->assertSuccessful();

    $response = davSyncReport($this, $basePath, $actor['header'], 'http://sabredav.org/ns/sync/1')
        ->assertStatus(207);

    expect(rfc6578ResponseStatuses($response))->toBe([
        $basePath.'event-1.ics' => 200,
    ])
        ->and(rfc6578SyncToken($response))->toBe('http://sabredav.org/ns/sync/2');
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc6578.html#section-3.2
 */
it('[section 3.2] reports address book changes through WebDAV sync', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavAddressBook::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $basePath = '/dav/addressbooks/'.$owner->getKey().'/personal/';

    davPut($this, $basePath.'contact-1.vcf', $actor['header'], contactCardPayload([
        'UID' => 'contact-1',
        'FN' => 'Ada Lovelace',
        'N' => ['value' => 'Lovelace;Ada;;;'],
        'EMAIL' => 'ada@example.com',
    ]), 'text/vcard')->assertSuccessful();

    $response = davSyncReport($this, $basePath, $actor['header'], 'http://sabredav.org/ns/sync/1')
        ->assertStatus(207);

    expect(rfc6578ResponseStatuses($response))->toBe([
        $basePath.'contact-1.vcf' => 200,
    ])
        ->and(rfc6578SyncToken($response))->toBe('http://sabredav.org/ns/sync/2');
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc6578.html#section-3.4
 */
it('[section 3.4] treats an empty sync token as an initial calendar sync', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $basePath = '/dav/calendars/'.$owner->getKey().'/personal/';

    davPut($this, $basePath.'event-1.ics', $actor['header'], calendarObjectPayload('VEVENT', [
        'UID' => 'event-1',
        'DTSTAMP' => '20260603T000000Z',
        'SUMMARY' => 'Deep Work',
        'DTSTART' => '20260603T070000Z',
        'DTEND' => '20260603T083000Z',
    ]), 'text/calendar')->assertSuccessful();

    davPut($this, $basePath.'event-2.ics', $actor['header'], calendarObjectPayload('VEVENT', [
        'UID' => 'event-2',
        'DTSTAMP' => '20260604T000000Z',
        'SUMMARY' => 'Planning',
        'DTSTART' => '20260604T070000Z',
        'DTEND' => '20260604T083000Z',
    ]), 'text/calendar')->assertSuccessful();

    $response = davSyncReport($this, $basePath, $actor['header'], null)
        ->assertStatus(207);

    expect(rfc6578ResponseStatuses($response))->toBe([
        $basePath.'event-1.ics' => 200,
        $basePath.'event-2.ics' => 200,
    ])
        ->and(rfc6578SyncToken($response))->toBe('http://sabredav.org/ns/sync/3');
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc6578.html#section-3.2
 * @see https://www.rfc-editor.org/rfc/rfc6578.html#section-3.5.2
 */
it('[sections 3.2 and 3.5.2] reports deleted resources with a 404 response', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $basePath = '/dav/calendars/'.$owner->getKey().'/personal/';

    davPut($this, $basePath.'event-1.ics', $actor['header'], calendarObjectPayload('VEVENT', [
        'UID' => 'event-1',
        'DTSTAMP' => '20260603T000000Z',
        'SUMMARY' => 'Deep Work',
        'DTSTART' => '20260603T070000Z',
        'DTEND' => '20260603T083000Z',
    ]), 'text/calendar')->assertSuccessful();

    $this->callDav('DELETE', $basePath.'event-1.ics', $actor['header'])
        ->assertSuccessful();

    $response = davSyncReport($this, $basePath, $actor['header'], 'http://sabredav.org/ns/sync/2')
        ->assertStatus(207);

    expect(rfc6578ResponseStatuses($response))->toBe([
        $basePath.'event-1.ics' => 404,
    ])
        ->and(rfc6578SyncToken($response))->toBe('http://sabredav.org/ns/sync/3');
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc6578.html#section-3.2
 */
it('[section 3.2] rejects invalid sync tokens', function (string $syncToken): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    davSyncReport(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/',
        $actor['header'],
        $syncToken,
    )->assertForbidden();
})->with([
    'malformed token' => 'garbage',
    'future token' => 'http://sabredav.org/ns/sync/999',
]);

/**
 * @see https://www.rfc-editor.org/rfc/rfc6578.html#section-3.6
 * @see https://www.rfc-editor.org/rfc/rfc6578.html#section-3.7
 */
it('[sections 3.6 and 3.7] returns a page token for truncated sync reports', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $basePath = '/dav/calendars/'.$owner->getKey().'/personal/';

    davPut($this, $basePath.'event-1.ics', $actor['header'], calendarObjectPayload('VEVENT', [
        'UID' => 'event-1',
        'DTSTAMP' => '20260603T000000Z',
        'SUMMARY' => 'Deep Work',
        'DTSTART' => '20260603T070000Z',
        'DTEND' => '20260603T083000Z',
    ]), 'text/calendar')->assertSuccessful();

    davPut($this, $basePath.'event-2.ics', $actor['header'], calendarObjectPayload('VEVENT', [
        'UID' => 'event-2',
        'DTSTAMP' => '20260604T000000Z',
        'SUMMARY' => 'Planning',
        'DTSTART' => '20260604T070000Z',
        'DTEND' => '20260604T083000Z',
    ]), 'text/calendar')->assertSuccessful();

    $response = davSyncReport($this, $basePath, $actor['header'], 'http://sabredav.org/ns/sync/1', 1)
        ->assertStatus(207);

    expect(rfc6578ResponseStatuses($response))->toBe([
        $basePath => 507,
        $basePath.'event-1.ics' => 200,
    ])
        ->and(rfc6578SyncToken($response))->toBe('http://sabredav.org/ns/sync/2');

    $nextResponse = davSyncReport($this, $basePath, $actor['header'], rfc6578SyncToken($response), 1)
        ->assertStatus(207);

    expect(rfc6578ResponseStatuses($nextResponse))->toBe([
        $basePath.'event-2.ics' => 200,
    ])
        ->and(rfc6578SyncToken($nextResponse))->toBe('http://sabredav.org/ns/sync/3');
});
