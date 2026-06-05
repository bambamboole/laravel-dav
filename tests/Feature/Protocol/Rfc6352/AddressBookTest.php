<?php

use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCard;
use Illuminate\Testing\TestResponse;

function rfc6352PropertyElement(TestResponse $response, string $namespace, string $localName): DOMElement
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

function rfc6352PropertyText(TestResponse $response, string $namespace, string $localName): string
{
    return trim(rfc6352PropertyElement($response, $namespace, $localName)->textContent);
}

/**
 * @return array<int, string>
 */
function rfc6352ResourceTypes(TestResponse $response): array
{
    $element = rfc6352PropertyElement($response, 'DAV:', 'resourcetype');
    $types = [];

    foreach ($element->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $types[] = '{'.$child->namespaceURI.'}'.$child->localName;
        }
    }

    sort($types);

    return $types;
}

it('[section 6.2.1] creates and deletes an address book collection through CardDAV', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    $this->callDav('MKCOL', '/dav/addressbooks/'.$owner->getKey().'/work/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:mkcol xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav">
            <d:set>
                <d:prop>
                    <d:resourcetype>
                        <d:collection />
                        <card:addressbook />
                    </d:resourcetype>
                    <d:displayname>Work Contacts</d:displayname>
                    <card:addressbook-description>Team directory</card:addressbook-description>
                </d:prop>
            </d:set>
        </d:mkcol>
        XML)
        ->assertCreated();

    $addressBook = DavAddressBook::query()
        ->where('user_id', $owner->getKey())
        ->where('uri', 'work')
        ->firstOrFail();

    expect($addressBook)
        ->display_name->toBe('Work Contacts')
        ->description->toBe('Team directory');

    $response = $this->callDav('PROPFIND', '/dav/addressbooks/'.$owner->getKey().'/work/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav">
            <d:prop>
                <d:resourcetype />
                <d:displayname />
                <card:addressbook-description />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207);

    expect(rfc6352ResourceTypes($response))->toBe([
        '{DAV:}collection',
        '{urn:ietf:params:xml:ns:carddav}addressbook',
    ])
        ->and(rfc6352PropertyText($response, 'DAV:', 'displayname'))->toBe('Work Contacts')
        ->and(rfc6352PropertyText($response, 'urn:ietf:params:xml:ns:carddav', 'addressbook-description'))->toBe('Team directory');

    $this->callDav('DELETE', '/dav/addressbooks/'.$owner->getKey().'/work/', $actor['header'])
        ->assertSuccessful();

    expect(DavAddressBook::query()->whereKey($addressBook->getKey())->exists())->toBeFalse();

    $this->callDav('PROPFIND', '/dav/addressbooks/'.$owner->getKey().'/work/', $actor['header'])
        ->assertNotFound();
});

it('[sections 6.2.2 and 6.2.3] exposes address book collection properties through PROPFIND', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavAddressBook::factory()->create([
        'user_id' => $owner->getKey(),
        'uri' => 'personal',
        'display_name' => 'Personal Contacts',
        'description' => 'People',
        'sync_token' => 7,
    ]);

    $response = $this->callDav('PROPFIND', '/dav/addressbooks/'.$owner->getKey().'/personal/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav" xmlns:cs="http://calendarserver.org/ns/">
            <d:prop>
                <d:resourcetype />
                <d:displayname />
                <card:addressbook-description />
                <cs:getctag />
                <d:sync-token />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207);

    expect(rfc6352ResourceTypes($response))->toBe([
        '{DAV:}collection',
        '{urn:ietf:params:xml:ns:carddav}addressbook',
    ])
        ->and(rfc6352PropertyText($response, 'DAV:', 'displayname'))->toBe('Personal Contacts')
        ->and(rfc6352PropertyText($response, 'urn:ietf:params:xml:ns:carddav', 'addressbook-description'))->toBe('People')
        ->and(rfc6352PropertyText($response, 'http://calendarserver.org/ns/', 'getctag'))->toBe('7')
        ->and(rfc6352PropertyText($response, 'DAV:', 'sync-token'))->toBe('http://sabredav.org/ns/sync/7');
});

it('[section 6.2.3] updates address book collection properties through PROPPATCH', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavAddressBook::factory()->create([
        'user_id' => $owner->getKey(),
        'uri' => 'personal',
        'display_name' => 'Personal Contacts',
        'description' => 'People',
    ]);

    $this->callDav('PROPPATCH', '/dav/addressbooks/'.$owner->getKey().'/personal/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propertyupdate xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav">
            <d:set>
                <d:prop>
                    <d:displayname>Team Contacts</d:displayname>
                    <card:addressbook-description>Team directory</card:addressbook-description>
                </d:prop>
            </d:set>
        </d:propertyupdate>
        XML)
        ->assertStatus(207);

    $addressBook = DavAddressBook::query()
        ->where('user_id', $owner->getKey())
        ->where('uri', 'personal')
        ->firstOrFail();

    expect($addressBook)
        ->display_name->toBe('Team Contacts')
        ->description->toBe('Team directory');

    $response = $this->callDav('PROPFIND', '/dav/addressbooks/'.$owner->getKey().'/personal/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav">
            <d:prop>
                <d:displayname />
                <card:addressbook-description />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207);

    expect(rfc6352PropertyText($response, 'DAV:', 'displayname'))->toBe('Team Contacts')
        ->and(rfc6352PropertyText($response, 'urn:ietf:params:xml:ns:carddav', 'addressbook-description'))->toBe('Team directory');
});

it('[sections 5.1 and 6.3.2] puts fetches and deletes a contact card through CardDAV', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavAddressBook::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $payload = vcard(<<<'VCF'
        BEGIN:VCARD
        VERSION:3.0
        PRODID:-//Life OS//Tests//EN
        UID:contact-1
        FN:Ada Lovelace
        N:Lovelace;Ada;;;
        EMAIL;TYPE=work:ada@example.com
        END:VCARD
        VCF);

    $path = '/dav/addressbooks/'.$owner->getKey().'/personal/contact-1.vcf';

    davPut($this, $path, $actor['header'], $payload, 'text/vcard')->assertSuccessful();

    expect(DavCard::query()->where('uri', 'contact-1.vcf')->first())
        ->not->toBeNull()
        ->card_data->toBe($payload);

    $this->withHeaders(['Authorization' => $actor['header']])
        ->get($path)
        ->assertSuccessful()
        ->assertContent($payload);

    $this->withHeaders(['Authorization' => $actor['header']])
        ->delete($path)
        ->assertSuccessful();

    $this->withHeaders(['Authorization' => $actor['header']])
        ->get($path)
        ->assertNotFound();
});
