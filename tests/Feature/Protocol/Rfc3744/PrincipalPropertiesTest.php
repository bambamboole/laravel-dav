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
