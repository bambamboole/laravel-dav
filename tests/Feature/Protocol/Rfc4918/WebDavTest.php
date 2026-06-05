<?php

use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Illuminate\Testing\TestResponse;

function rfc4918Document(TestResponse $response): DOMDocument
{
    $document = new DOMDocument;
    $document->loadXML($response->getContent());

    return $document;
}

/**
 * @return array<int, string>
 */
function rfc4918ResponseHrefs(TestResponse $response): array
{
    $xpath = new DOMXPath(rfc4918Document($response));
    $nodes = $xpath->query('/*[namespace-uri()="DAV:" and local-name()="multistatus"]/*[namespace-uri()="DAV:" and local-name()="response"]/*[namespace-uri()="DAV:" and local-name()="href"]');

    expect($nodes)->not->toBeFalse();

    $hrefs = [];

    foreach ($nodes as $node) {
        $hrefs[] = trim($node->textContent);
    }

    sort($hrefs);

    return $hrefs;
}

/**
 * @return array<string, int>
 */
function rfc4918PropertyStatuses(TestResponse $response): array
{
    $xpath = new DOMXPath(rfc4918Document($response));
    $propstats = $xpath->query('//*[namespace-uri()="DAV:" and local-name()="propstat"]');

    expect($propstats)->not->toBeFalse();

    $statuses = [];

    foreach ($propstats as $propstat) {
        if (! $propstat instanceof DOMElement) {
            continue;
        }

        $status = $xpath->query('./*[namespace-uri()="DAV:" and local-name()="status"]', $propstat);
        $properties = $xpath->query('./*[namespace-uri()="DAV:" and local-name()="prop"]/*', $propstat);

        expect($status)->not->toBeFalse()
            ->and($status->length)->toBe(1)
            ->and($properties)->not->toBeFalse();

        $matched = preg_match('/\s(\d{3})\s/', $status->item(0)->textContent, $matches);

        expect($matched)->toBe(1);

        foreach ($properties as $property) {
            if ($property instanceof DOMElement) {
                $statuses['{'.$property->namespaceURI.'}'.$property->localName] = (int) $matches[1];
            }
        }
    }

    ksort($statuses);

    return $statuses;
}

/**
 * @see https://www.rfc-editor.org/rfc/rfc4918.html#section-9.1
 */
it('[section 9.1] serves the dav root through PROPFIND', function (): void {
    $actor = davActor();

    $this->callDav('PROPFIND', '/dav/', $actor['header'])
        ->assertStatus(207)
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('/dav/principals/', false);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4918.html#section-9.1
 * @see https://www.rfc-editor.org/rfc/rfc4918.html#section-15.2
 */
it('[sections 9.1 and 15.2] returns the principal displayname through PROPFIND', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    $this->callDav('PROPFIND', '/dav/principals/'.$owner->getKey().'/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:displayname />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207)
        ->assertSee('/dav/principals/'.$owner->getKey().'/', false)
        ->assertSee($owner->name, false);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4918.html#section-9.2
 */
it('[section 9.2] persists a custom property through PROPPATCH and reads it back', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $path = '/dav/calendars/'.$owner->getKey().'/personal/';

    $this->callDav('PROPPATCH', $path, $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propertyupdate xmlns:d="DAV:" xmlns:x="http://life-os.test/ns">
            <d:set>
                <d:prop>
                    <x:custom-flag>enabled</x:custom-flag>
                </d:prop>
            </d:set>
        </d:propertyupdate>
        XML)
        ->assertStatus(207);

    $this->callDav('PROPFIND', $path, $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:" xmlns:x="http://life-os.test/ns">
            <d:prop>
                <x:custom-flag />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207)
        ->assertSee('enabled', false);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4918.html#section-10.1
 * @see https://www.rfc-editor.org/rfc/rfc4918.html#section-18.1
 */
it('[sections 10.1 and 18.1] advertises WebDAV compliance and allowed methods through OPTIONS', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create([
        'user_id' => $owner->getKey(),
        'uri' => 'personal',
    ]);

    $response = $this->callDav('OPTIONS', '/dav/calendars/'.$owner->getKey().'/personal/', $actor['header'])
        ->assertOk()
        ->assertHeader('MS-Author-Via', 'DAV')
        ->assertHeader('Content-Length', '0');

    $davFeatures = array_map('trim', explode(',', (string) $response->headers->get('DAV')));
    $allowedMethods = array_map('trim', explode(',', (string) $response->headers->get('Allow')));

    expect($davFeatures)->toContain('1', '3', 'calendar-access', 'addressbook')
        ->and($allowedMethods)->toContain('OPTIONS', 'PROPFIND', 'PROPPATCH', 'DELETE');
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4918.html#section-9.1
 * @see https://www.rfc-editor.org/rfc/rfc4918.html#section-10.2
 */
it('[sections 9.1 and 10.2] limits PROPFIND responses by Depth header', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    $calendar = DavCalendar::factory()->create([
        'user_id' => $owner->getKey(),
        'uri' => 'personal',
    ]);

    DavCalendarObject::factory()->for($calendar, 'calendar')->create([
        'uri' => 'event-1.ics',
    ]);

    $path = '/dav/calendars/'.$owner->getKey().'/personal/';
    $childPath = $path.'event-1.ics';
    $body = <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:resourcetype />
                <d:getetag />
            </d:prop>
        </d:propfind>
        XML;

    $depthZero = $this->callDav('PROPFIND', $path, $actor['header'], $body, [
        'HTTP_DEPTH' => '0',
    ])->assertStatus(207);

    expect(rfc4918ResponseHrefs($depthZero))->toBe([$path]);

    $depthOne = $this->callDav('PROPFIND', $path, $actor['header'], $body, [
        'HTTP_DEPTH' => '1',
    ])->assertStatus(207);

    expect(rfc4918ResponseHrefs($depthOne))->toBe([$path, $childPath]);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4918.html#section-9.2
 * @see https://www.rfc-editor.org/rfc/rfc4918.html#section-9.2.1
 * @see https://www.rfc-editor.org/rfc/rfc4918.html#section-11.4
 */
it('[sections 9.2, 9.2.1 and 11.4] rejects protected property updates atomically', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create([
        'user_id' => $owner->getKey(),
        'uri' => 'personal',
    ]);

    $path = '/dav/calendars/'.$owner->getKey().'/personal/';

    $response = $this->callDav('PROPPATCH', $path, $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propertyupdate xmlns:d="DAV:" xmlns:x="http://life-os.test/ns">
            <d:set>
                <d:prop>
                    <d:getetag>"client-owned"</d:getetag>
                    <x:custom-flag>enabled</x:custom-flag>
                </d:prop>
            </d:set>
        </d:propertyupdate>
        XML)
        ->assertStatus(207);

    expect(rfc4918PropertyStatuses($response))->toMatchArray([
        '{DAV:}getetag' => 403,
        '{http://life-os.test/ns}custom-flag' => 424,
    ]);

    $readBack = $this->callDav('PROPFIND', $path, $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:" xmlns:x="http://life-os.test/ns">
            <d:prop>
                <x:custom-flag />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])->assertStatus(207);

    expect(rfc4918PropertyStatuses($readBack))->toBe([
        '{http://life-os.test/ns}custom-flag' => 404,
    ]);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc4918.html#section-9.6
 * @see https://www.rfc-editor.org/rfc/rfc4918.html#section-9.6.1
 */
it('[sections 9.6 and 9.6.1] deletes collection resources through DELETE', function (string $collectionType): void {
    $actor = davActor();
    $owner = $actor['owner'];

    $collection = match ($collectionType) {
        'calendar' => DavCalendar::factory()->create([
            'user_id' => $owner->getKey(),
            'uri' => 'personal',
        ]),
        'address book' => DavAddressBook::factory()->create([
            'user_id' => $owner->getKey(),
            'uri' => 'personal',
        ]),
    };

    $path = match ($collectionType) {
        'calendar' => '/dav/calendars/'.$owner->getKey().'/personal/',
        'address book' => '/dav/addressbooks/'.$owner->getKey().'/personal/',
    };

    $this->callDav('DELETE', $path, $actor['header'])
        ->assertNoContent();

    expect($collection->fresh())->toBeNull();

    $this->callDav('PROPFIND', $path, $actor['header'])
        ->assertNotFound();
})->with([
    'calendar' => 'calendar',
    'address book' => 'address book',
]);
