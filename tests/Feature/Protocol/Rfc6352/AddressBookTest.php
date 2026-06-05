<?php

use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCard;
use Illuminate\Testing\TestResponse;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;

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
 * @return array<string, VCard>
 */
function rfc6352AddressDataByName(TestResponse $response): array
{
    $document = new DOMDocument;
    $document->loadXML($response->getContent());

    $xpath = new DOMXPath($document);
    $responses = $xpath->query('//*[namespace-uri()="DAV:" and local-name()="response"]');

    expect($responses)->not->toBeFalse();

    $cards = [];

    foreach ($responses as $responseElement) {
        if (! $responseElement instanceof DOMElement) {
            continue;
        }

        $href = $xpath->query('./*[namespace-uri()="DAV:" and local-name()="href"]', $responseElement);
        $addressData = $xpath->query('.//*[namespace-uri()="urn:ietf:params:xml:ns:carddav" and local-name()="address-data"]', $responseElement);

        expect($href)->not->toBeFalse()
            ->and($href->length)->toBe(1)
            ->and($addressData)->not->toBeFalse()
            ->and($addressData->length)->toBe(1);

        $card = Reader::read(trim($addressData->item(0)->textContent));

        expect($card)->toBeInstanceOf(VCard::class);

        $cards[basename(trim($href->item(0)->textContent))] = $card;
    }

    ksort($cards);

    return $cards;
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

/**
 * @see https://www.rfc-editor.org/rfc/rfc6352.html#section-6.2.1
 */
it('[section 6.2.1] creates an address book collection through CardDAV', function (): void {
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
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc6352.html#section-6.2.2
 * @see https://www.rfc-editor.org/rfc/rfc6352.html#section-6.2.3
 */
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

/**
 * @see https://www.rfc-editor.org/rfc/rfc6352.html#section-6.2.3
 */
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

/**
 * @see https://www.rfc-editor.org/rfc/rfc6352.html#section-8.6
 */
it('[section 8.6] returns matching contacts through addressbook-query', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavAddressBook::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $basePath = '/dav/addressbooks/'.$owner->getKey().'/personal/';

    davPut($this, $basePath.'ada.vcf', $actor['header'], contactCardPayload([
        'UID' => 'contact-1',
        'FN' => 'Ada Lovelace',
        'N' => ['value' => 'Lovelace;Ada;;;'],
        'EMAIL' => 'ada@example.com',
    ]), 'text/vcard')->assertSuccessful();

    davPut($this, $basePath.'grace.vcf', $actor['header'], contactCardPayload([
        'UID' => 'contact-2',
        'FN' => 'Grace Hopper',
        'N' => ['value' => 'Hopper;Grace;;;'],
        'EMAIL' => 'grace@example.com',
    ]), 'text/vcard')->assertSuccessful();

    $response = $this->callDav('REPORT', $basePath, $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <card:addressbook-query xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav">
            <d:prop>
                <d:getetag />
                <card:address-data />
            </d:prop>
            <card:filter test="allof">
                <card:prop-filter name="FN">
                    <card:text-match collation="i;unicode-casemap" match-type="contains">Ada</card:text-match>
                </card:prop-filter>
            </card:filter>
        </card:addressbook-query>
        XML, [
        'HTTP_DEPTH' => '1',
    ])
        ->assertStatus(207);

    $cards = rfc6352AddressDataByName($response);

    try {
        expect(array_keys($cards))->toBe(['ada.vcf'])
            ->and((string) $cards['ada.vcf']->UID)->toBe('contact-1')
            ->and((string) $cards['ada.vcf']->FN)->toBe('Ada Lovelace');
    } finally {
        foreach ($cards as $card) {
            $card->destroy();
        }
    }
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc6352.html#section-8.7
 */
it('[section 8.7] returns requested contacts through addressbook-multiget', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavAddressBook::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $basePath = '/dav/addressbooks/'.$owner->getKey().'/personal/';

    davPut($this, $basePath.'ada.vcf', $actor['header'], contactCardPayload([
        'UID' => 'contact-1',
        'FN' => 'Ada Lovelace',
        'N' => ['value' => 'Lovelace;Ada;;;'],
        'EMAIL' => 'ada@example.com',
    ]), 'text/vcard')->assertSuccessful();

    davPut($this, $basePath.'grace.vcf', $actor['header'], contactCardPayload([
        'UID' => 'contact-2',
        'FN' => 'Grace Hopper',
        'N' => ['value' => 'Hopper;Grace;;;'],
        'EMAIL' => 'grace@example.com',
    ]), 'text/vcard')->assertSuccessful();

    $response = $this->callDav('REPORT', $basePath, $actor['header'], <<<XML
        <?xml version="1.0" encoding="utf-8" ?>
        <card:addressbook-multiget xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav">
            <d:prop>
                <d:getetag />
                <card:address-data />
            </d:prop>
            <d:href>{$basePath}ada.vcf</d:href>
            <d:href>{$basePath}grace.vcf</d:href>
        </card:addressbook-multiget>
        XML, [
        'HTTP_DEPTH' => '1',
    ])
        ->assertStatus(207);

    $cards = rfc6352AddressDataByName($response);

    try {
        expect(array_keys($cards))->toBe(['ada.vcf', 'grace.vcf'])
            ->and((string) $cards['ada.vcf']->FN)->toBe('Ada Lovelace')
            ->and((string) $cards['grace.vcf']->FN)->toBe('Grace Hopper');
    } finally {
        foreach ($cards as $card) {
            $card->destroy();
        }
    }
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc6352.html#section-5.1
 * @see https://www.rfc-editor.org/rfc/rfc6352.html#section-6.3.2
 */
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

/**
 * @see https://www.rfc-editor.org/rfc/rfc6352.html#section-5.1
 * @see https://www.rfc-editor.org/rfc/rfc6350.html
 */
it('[section 5.1] stores a vCard 4.0 card losslessly and serves it back under content negotiation', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavAddressBook::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $payload = vcard(<<<'VCF'
        BEGIN:VCARD
        VERSION:4.0
        PRODID:-//Life OS//Tests//EN
        UID:urn:uuid:vcard4-contact
        FN:Ada Lovelace
        N:Lovelace;Ada;;;
        KIND:individual
        GENDER:F
        ANNIVERSARY:20100615
        EMAIL;PREF=1:ada@example.com
        EMAIL;TYPE=work:ada@work.example.com
        TEL;TYPE=cell;PREF=1:+1-555-0100
        ADR;TYPE=home:;;12 Analytical St;London;;EC1;UK
        END:VCARD
        VCF);

    $path = '/dav/addressbooks/'.$owner->getKey().'/personal/vcard4.vcf';

    davPut($this, $path, $actor['header'], $payload, 'text/vcard')->assertSuccessful();

    expect(DavCard::query()->where('uri', 'vcard4.vcf')->first())
        ->not->toBeNull()
        ->card_data->toBe($payload);

    $this->withHeaders(['Authorization' => $actor['header']])
        ->get($path, ['Accept' => 'text/vcard; version=4.0'])
        ->assertSuccessful()
        ->assertContent($payload);

    $downgraded = $this->withHeaders(['Authorization' => $actor['header']])
        ->get($path, ['Accept' => 'text/vcard; version=3.0'])
        ->assertSuccessful()
        ->getContent();

    expect($downgraded)->toContain('VERSION:3.0')
        ->and($downgraded)->not->toContain('VERSION:4.0');
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc6352.html#section-6.2.2
 */
it('[section 6.2.2] advertises vCard 3.0 and 4.0 in supported-address-data', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavAddressBook::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $response = $this->callDav('PROPFIND', '/dav/addressbooks/'.$owner->getKey().'/personal/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:" xmlns:card="urn:ietf:params:xml:ns:carddav">
            <d:prop>
                <card:supported-address-data />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207);

    $document = new DOMDocument;
    $document->loadXML($response->getContent());
    $xpath = new DOMXPath($document);
    $types = $xpath->query('//*[namespace-uri()="urn:ietf:params:xml:ns:carddav" and local-name()="address-data-type"]');

    $versions = [];
    foreach ($types as $type) {
        if ($type instanceof DOMElement) {
            $versions[$type->getAttribute('content-type').';'.$type->getAttribute('version')] = true;
        }
    }

    expect($versions)->toHaveKey('text/vcard;3.0')
        ->and($versions)->toHaveKey('text/vcard;4.0');
});
