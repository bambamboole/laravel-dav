<?php

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarInstance;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Illuminate\Testing\TestResponse;
use Sabre\DAV\Sharing\Plugin as SharingPlugin;

function calendarSharingDocument(TestResponse $response): DOMDocument
{
    $document = new DOMDocument;
    $document->loadXML($response->getContent());

    return $document;
}

/**
 * @return array<int, string>
 */
function calendarSharingResponseHrefs(TestResponse $response): array
{
    $xpath = new DOMXPath(calendarSharingDocument($response));
    $nodes = $xpath->query('/*[namespace-uri()="DAV:" and local-name()="multistatus"]/*[namespace-uri()="DAV:" and local-name()="response"]/*[namespace-uri()="DAV:" and local-name()="href"]');

    expect($nodes)->not->toBeFalse();

    $hrefs = [];

    foreach ($nodes as $node) {
        $hrefs[] = trim($node->textContent);
    }

    sort($hrefs);

    return $hrefs;
}

function calendarSharingPropertyText(TestResponse $response, string $namespace, string $localName): string
{
    $xpath = new DOMXPath(calendarSharingDocument($response));
    $nodes = $xpath->query(sprintf('//*[namespace-uri()="%s" and local-name()="%s"]', $namespace, $localName));

    expect($nodes)->not->toBeFalse()
        ->and($nodes->length)->toBeGreaterThan(0);

    return trim($nodes->item(0)->textContent);
}

/**
 * @return array<int, string>
 */
function calendarSharingResourceTypes(TestResponse $response): array
{
    $xpath = new DOMXPath(calendarSharingDocument($response));
    $nodes = $xpath->query('//*[namespace-uri()="DAV:" and local-name()="resourcetype"]/*');

    expect($nodes)->not->toBeFalse();

    $types = [];

    foreach ($nodes as $node) {
        if ($node instanceof DOMElement) {
            $types[] = '{'.$node->namespaceURI.'}'.$node->localName;
        }
    }

    sort($types);

    return $types;
}

function shareCalendar(mixed $test, string $path, string $authHeader, string $shareeEmail, string $access = 'read-write'): TestResponse
{
    $accessElement = $access === 'read-write' ? '<cs:read-write />' : '<cs:read />';

    return $test->callDav('POST', $path, $authHeader, <<<XML
        <?xml version="1.0" encoding="utf-8" ?>
        <cs:share xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/">
            <cs:set>
                <d:href>mailto:{$shareeEmail}</d:href>
                {$accessElement}
                <cs:common-name>Shared {$shareeEmail}</cs:common-name>
            </cs:set>
        </cs:share>
        XML);
}

function revokeCalendarShare(mixed $test, string $path, string $authHeader, string $shareeEmail): TestResponse
{
    return $test->callDav('POST', $path, $authHeader, <<<XML
        <?xml version="1.0" encoding="utf-8" ?>
        <cs:share xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/">
            <cs:remove>
                <d:href>mailto:{$shareeEmail}</d:href>
            </cs:remove>
        </cs:share>
        XML);
}

/**
 * @see https://sabre.io/dav/caldav-sharing/
 */
it('shares calendars through dav and enforces read write revoke access', function (): void {
    $ownerActor = davActor();
    $shareeActor = davActor();
    $owner = $ownerActor['owner'];
    $sharee = $shareeActor['owner'];

    DavCalendar::factory()->withInstance([
        'uri' => 'personal',
        'display_name' => 'Personal',
    ])->create([
        'owner_id' => $owner->getKey(),
    ]);

    $ownerPath = '/dav/calendars/'.$owner->getKey().'/personal/';

    shareCalendar($this, $ownerPath, $ownerActor['header'], $sharee->email)
        ->assertSuccessful()
        ->assertHeader('X-Sabre-Status', 'everything-went-well');

    $shareeInstance = DavCalendarInstance::query()
        ->where('owner_id', $sharee->getKey())
        ->where('access', SharingPlugin::ACCESS_READWRITE)
        ->firstOrFail();

    $shareePath = '/dav/calendars/'.$sharee->getKey().'/'.$shareeInstance->uri.'/';

    $homeResponse = $this->callDav('PROPFIND', '/dav/calendars/'.$sharee->getKey().'/', $shareeActor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:displayname />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '1',
    ])
        ->assertStatus(207);

    expect(calendarSharingResponseHrefs($homeResponse))->toContain($shareePath);

    $calendarResponse = $this->callDav('PROPFIND', $shareePath, $shareeActor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/">
            <d:prop>
                <d:resourcetype />
                <d:share-access />
                <d:share-resource-uri />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207);

    expect(calendarSharingResourceTypes($calendarResponse))->toContain('{http://calendarserver.org/ns/}shared')
        ->and(calendarSharingPropertyText($calendarResponse, 'DAV:', 'share-resource-uri'))->toBe('/ns/share/'.$shareeInstance->dav_calendar_id);

    davPut($this, $shareePath.'shared-event.ics', $shareeActor['header'], calendarObjectPayload('VEVENT', [
        'UID' => 'shared-event',
        'SUMMARY' => 'Shared planning',
        'DTSTART' => '20260601T090000Z',
        'DTEND' => '20260601T100000Z',
    ]), 'text/calendar')->assertSuccessful();

    expect(DavCalendarObject::query()->where('uri', 'shared-event.ics')->first())
        ->not->toBeNull()
        ->dav_calendar_id->toBe($shareeInstance->dav_calendar_id);

    $this->withHeaders(['Authorization' => $ownerActor['header']])
        ->get($ownerPath.'shared-event.ics')
        ->assertSuccessful();

    shareCalendar($this, $ownerPath, $ownerActor['header'], $sharee->email, 'read')
        ->assertSuccessful();

    $shareeInstance->refresh();

    expect($shareeInstance->access)->toBe(SharingPlugin::ACCESS_READ);

    davPut($this, $shareePath.'read-only-event.ics', $shareeActor['header'], calendarObjectPayload('VEVENT', [
        'UID' => 'read-only-event',
        'SUMMARY' => 'Should fail',
        'DTSTART' => '20260602T090000Z',
        'DTEND' => '20260602T100000Z',
    ]), 'text/calendar')->assertForbidden();

    $this->withHeaders(['Authorization' => $shareeActor['header']])
        ->get($shareePath.'shared-event.ics')
        ->assertSuccessful();

    revokeCalendarShare($this, $ownerPath, $ownerActor['header'], $sharee->email)
        ->assertSuccessful();

    expect($shareeInstance->fresh())->toBeNull()
        ->and(DavCalendarObject::query()->where('uri', 'shared-event.ics')->exists())->toBeTrue();

    $this->callDav('PROPFIND', $shareePath, $shareeActor['header'])
        ->assertNotFound();
});
