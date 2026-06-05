<?php

use Illuminate\Testing\TestResponse;

function rfc3744Document(TestResponse $response): DOMDocument
{
    $document = new DOMDocument;

    expect($document->loadXML($response->getContent()))->toBeTrue();

    return $document;
}

/**
 * @return array<int, string>
 */
function rfc3744PropertyHrefs(TestResponse $response, string $namespace, string $localName): array
{
    $document = rfc3744Document($response);
    $xpath = new DOMXPath($document);
    $nodes = $xpath->query(sprintf(
        '//*[namespace-uri()="%s" and local-name()="%s"]/*[namespace-uri()="DAV:" and local-name()="href"]',
        $namespace,
        $localName,
    ));

    expect($nodes)->not->toBeFalse();

    $hrefs = [];

    foreach ($nodes as $node) {
        $hrefs[] = trim($node->textContent);
    }

    sort($hrefs);

    return $hrefs;
}

function rfc3744PropertyText(TestResponse $response, string $namespace, string $localName): string
{
    $document = rfc3744Document($response);
    $xpath = new DOMXPath($document);
    $nodes = $xpath->query(sprintf(
        '//*[namespace-uri()="%s" and local-name()="%s"]',
        $namespace,
        $localName,
    ));

    expect($nodes)->not->toBeFalse()
        ->and($nodes->length)->toBeGreaterThan(0);

    return trim($nodes->item(0)->textContent);
}

function rfc3744PropPatchOk(TestResponse $response, string $namespace, string $localName): void
{
    $document = rfc3744Document($response);
    $xpath = new DOMXPath($document);
    $nodes = $xpath->query(sprintf(
        '//*[namespace-uri()="%s" and local-name()="%s"]/ancestor::*[namespace-uri()="DAV:" and local-name()="propstat"]/*[namespace-uri()="DAV:" and local-name()="status"]',
        $namespace,
        $localName,
    ));

    expect($nodes)->not->toBeFalse()
        ->and($nodes->length)->toBeGreaterThan(0)
        ->and((bool) preg_match('/\s2\d\d\s/', (string) $nodes->item(0)?->textContent))->toBeTrue();
}

/**
 * @return array<int, string>
 */
function rfc3744CurrentUserPrivileges(TestResponse $response): array
{
    $document = rfc3744Document($response);
    $xpath = new DOMXPath($document);
    $nodes = $xpath->query('//*[namespace-uri()="DAV:" and local-name()="current-user-privilege-set"]/*[namespace-uri()="DAV:" and local-name()="privilege"]/*');

    expect($nodes)->not->toBeFalse();

    $privileges = [];

    foreach ($nodes as $node) {
        if ($node instanceof DOMElement) {
            $privileges[] = '{'.$node->namespaceURI.'}'.$node->localName;
        }
    }

    sort($privileges);

    return $privileges;
}

/**
 * @return array<int, string>
 */
function rfc3744ResponseHrefs(TestResponse $response): array
{
    $document = rfc3744Document($response);
    $xpath = new DOMXPath($document);
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
 * @see https://www.rfc-editor.org/rfc/rfc3744.html#section-4.1
 * @see https://www.rfc-editor.org/rfc/rfc3744.html#section-4.2
 * @see https://www.rfc-editor.org/rfc/rfc3744.html#section-4.3
 * @see https://www.rfc-editor.org/rfc/rfc3744.html#section-4.4
 */
it('[sections 4.1, 4.2, 4.3, and 4.4] exposes principal URL, alternate URI, and empty group properties', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    $owner->forceFill([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ])->save();

    $response = $this->callDav('PROPFIND', '/dav/principals/'.$owner->getKey().'/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:displayname />
                <d:alternate-URI-set />
                <d:principal-URL />
                <d:group-member-set />
                <d:group-membership />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207);

    expect(rfc3744PropertyText($response, 'DAV:', 'displayname'))->toBe('Ada Lovelace')
        ->and(rfc3744PropertyHrefs($response, 'DAV:', 'alternate-URI-set'))->toBe(['mailto:ada@example.com'])
        ->and(rfc3744PropertyHrefs($response, 'DAV:', 'principal-URL'))->toBe(['/dav/principals/'.$owner->getKey().'/'])
        ->and(rfc3744PropertyHrefs($response, 'DAV:', 'group-member-set'))->toBe([])
        ->and(rfc3744PropertyHrefs($response, 'DAV:', 'group-membership'))->toBe([]);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc3744.html#section-5.4
 * @see https://www.rfc-editor.org/rfc/rfc3744.html#section-5.8
 */
it('[sections 5.4 and 5.8] exposes current user privileges and principal collection set', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    $response = $this->callDav('PROPFIND', '/dav/principals/'.$owner->getKey().'/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:current-user-privilege-set />
                <d:principal-collection-set />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207);

    expect(rfc3744PropertyHrefs($response, 'DAV:', 'principal-collection-set'))->toBe(['/dav/principals/'])
        ->and(rfc3744CurrentUserPrivileges($response))->toContain(
            '{DAV:}all',
            '{DAV:}read',
            '{DAV:}read-acl',
            '{DAV:}read-current-user-privilege-set',
            '{DAV:}write',
            '{DAV:}write-acl',
            '{DAV:}write-content',
            '{DAV:}write-properties',
        );
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc3744.html#section-4.1
 * @see https://datatracker.ietf.org/doc/html/rfc4791#section-6
 */
it('[section 4.1 and RFC 4791 section 6] exposes calendar proxy principals as principal children', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    $response = $this->callDav('PROPFIND', '/dav/principals/'.$owner->getKey().'/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:displayname />
                <d:resourcetype />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '1',
    ])
        ->assertStatus(207);

    expect(rfc3744ResponseHrefs($response))->toBe([
        '/dav/principals/'.$owner->getKey().'/',
        '/dav/principals/'.$owner->getKey().'/calendar-proxy-read/',
        '/dav/principals/'.$owner->getKey().'/calendar-proxy-write/',
    ]);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc3744.html#section-4.3
 * @see https://www.rfc-editor.org/rfc/rfc3744.html#section-4.4
 * @see https://datatracker.ietf.org/doc/html/rfc4791#section-6
 */
it('[sections 4.3 and 4.4 plus RFC 4791 section 6] stores calendar proxy group memberships', function (): void {
    $delegator = davActor();
    $delegate = davActor();

    $response = $this->callDav('PROPPATCH', '/dav/principals/'.$delegator['owner']->getKey().'/calendar-proxy-read/', $delegator['header'], '
        <d:propertyupdate xmlns:d="DAV:">
            <d:set>
                <d:prop>
                    <d:group-member-set>
                        <d:href>/dav/principals/'.$delegate['owner']->getKey().'/</d:href>
                    </d:group-member-set>
                </d:prop>
            </d:set>
        </d:propertyupdate>
        ')
        ->assertStatus(207);

    rfc3744PropPatchOk($response, 'DAV:', 'group-member-set');

    $readProxyResponse = $this->callDav('PROPFIND', '/dav/principals/'.$delegator['owner']->getKey().'/calendar-proxy-read/', $delegator['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:group-member-set />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207);

    $delegateResponse = $this->callDav('PROPFIND', '/dav/principals/'.$delegate['owner']->getKey().'/', $delegate['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:group-membership />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207);

    expect(rfc3744PropertyHrefs($readProxyResponse, 'DAV:', 'group-member-set'))->toBe(['/dav/principals/'.$delegate['owner']->getKey().'/'])
        ->and(rfc3744PropertyHrefs($delegateResponse, 'DAV:', 'group-membership'))->toBe(['/dav/principals/'.$delegator['owner']->getKey().'/calendar-proxy-read/']);

    $revokeResponse = $this->callDav('PROPPATCH', '/dav/principals/'.$delegator['owner']->getKey().'/calendar-proxy-read/', $delegator['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propertyupdate xmlns:d="DAV:">
            <d:set>
                <d:prop>
                    <d:group-member-set />
                </d:prop>
            </d:set>
        </d:propertyupdate>
        XML)
        ->assertStatus(207);

    rfc3744PropPatchOk($revokeResponse, 'DAV:', 'group-member-set');

    $revokedResponse = $this->callDav('PROPFIND', '/dav/principals/'.$delegate['owner']->getKey().'/', $delegate['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:group-membership />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207);

    expect(rfc3744PropertyHrefs($revokedResponse, 'DAV:', 'group-membership'))->toBe([]);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc3744.html#section-4.3
 * @see https://www.rfc-editor.org/rfc/rfc3744.html#section-4.4
 * @see https://datatracker.ietf.org/doc/html/rfc4791#section-6
 */
it('[sections 4.3 and 4.4 plus RFC 4791 section 6] separates read and write proxy group memberships', function (): void {
    $delegator = davActor();
    $readDelegate = davActor();
    $writeDelegate = davActor();

    $this->callDav('PROPPATCH', '/dav/principals/'.$delegator['owner']->getKey().'/calendar-proxy-read/', $delegator['header'], '
        <d:propertyupdate xmlns:d="DAV:">
            <d:set>
                <d:prop>
                    <d:group-member-set>
                        <d:href>/dav/principals/'.$readDelegate['owner']->getKey().'/</d:href>
                    </d:group-member-set>
                </d:prop>
            </d:set>
        </d:propertyupdate>
        ')
        ->assertStatus(207);

    $this->callDav('PROPPATCH', '/dav/principals/'.$delegator['owner']->getKey().'/calendar-proxy-write/', $delegator['header'], '
        <d:propertyupdate xmlns:d="DAV:">
            <d:set>
                <d:prop>
                    <d:group-member-set>
                        <d:href>/dav/principals/'.$writeDelegate['owner']->getKey().'/</d:href>
                    </d:group-member-set>
                </d:prop>
            </d:set>
        </d:propertyupdate>
        ')
        ->assertStatus(207);

    $readResponse = $this->callDav('PROPFIND', '/dav/principals/'.$readDelegate['owner']->getKey().'/', $readDelegate['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:group-membership />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207);

    $writeResponse = $this->callDav('PROPFIND', '/dav/principals/'.$writeDelegate['owner']->getKey().'/', $writeDelegate['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:group-membership />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207);

    expect(rfc3744PropertyHrefs($readResponse, 'DAV:', 'group-membership'))->toBe(['/dav/principals/'.$delegator['owner']->getKey().'/calendar-proxy-read/'])
        ->and(rfc3744PropertyHrefs($writeResponse, 'DAV:', 'group-membership'))->toBe(['/dav/principals/'.$delegator['owner']->getKey().'/calendar-proxy-write/']);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc3744.html#section-4.1
 * @see https://www.rfc-editor.org/rfc/rfc3744.html#section-9.4
 */
it('[sections 4.1 and 9.4] finds a principal by email address property', function (): void {
    $match = principalSearchActor('Ada Lovelace');
    $other = principalSearchActor('Grace Hopper');

    $match['owner']->forceFill(['email' => 'ada@example.com'])->save();
    $other['owner']->forceFill(['email' => 'grace@example.com'])->save();

    $response = principalPropertySearchReport($this, $match['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:principal-property-search xmlns:d="DAV:" xmlns:s="http://sabredav.org/ns">
            <d:property-search>
                <d:prop>
                    <s:email-address />
                </d:prop>
                <d:match>ada@example.com</d:match>
            </d:property-search>
            <d:prop>
                <d:displayname />
                <d:alternate-URI-set />
            </d:prop>
        </d:principal-property-search>
        XML)
        ->assertStatus(207);

    expect(rfc3744ResponseHrefs($response))->toBe(['/dav/principals/'.$match['owner']->getKey().'/'])
        ->and(rfc3744PropertyText($response, 'DAV:', 'displayname'))->toBe('Ada Lovelace')
        ->and(rfc3744PropertyHrefs($response, 'DAV:', 'alternate-URI-set'))->toBe(['mailto:ada@example.com']);
});
