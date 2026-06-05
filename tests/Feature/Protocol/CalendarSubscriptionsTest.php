<?php

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarSubscription;
use Illuminate\Testing\TestResponse;

function calendarSubscriptionDocument(TestResponse $response): DOMDocument
{
    $document = new DOMDocument;
    $document->loadXML($response->getContent());

    return $document;
}

function calendarSubscriptionPropertyText(TestResponse $response, string $namespace, string $localName): string
{
    $xpath = new DOMXPath(calendarSubscriptionDocument($response));
    $nodes = $xpath->query(sprintf('//*[namespace-uri()="%s" and local-name()="%s"]', $namespace, $localName));

    expect($nodes)->not->toBeFalse()
        ->and($nodes->length)->toBeGreaterThan(0);

    return trim($nodes->item(0)->textContent);
}

/**
 * @return array<int, string>
 */
function calendarSubscriptionResourceTypes(TestResponse $response): array
{
    $xpath = new DOMXPath(calendarSubscriptionDocument($response));
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

/**
 * @return array<string, int>
 */
function calendarSubscriptionPropertyStatuses(TestResponse $response): array
{
    $xpath = new DOMXPath(calendarSubscriptionDocument($response));
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

it('creates, reads, updates, and deletes calendar subscriptions through dav', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];
    $path = '/dav/calendars/'.$owner->getKey().'/holidays/';

    $this->callDav('MKCOL', $path, $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:mkcol xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/" xmlns:ical="http://apple.com/ns/ical/">
            <d:set>
                <d:prop>
                    <d:resourcetype>
                        <d:collection />
                        <cs:subscribed />
                    </d:resourcetype>
                    <cs:source>
                        <d:href>https://example.com/holidays.ics</d:href>
                    </cs:source>
                    <d:displayname>Holidays</d:displayname>
                    <ical:calendar-color>#B8255FFF</ical:calendar-color>
                    <ical:refreshrate>P1D</ical:refreshrate>
                    <ical:calendar-order>10</ical:calendar-order>
                    <cs:subscribed-strip-todos />
                    <cs:subscribed-strip-attachments />
                </d:prop>
            </d:set>
        </d:mkcol>
        XML)
        ->assertCreated();

    $subscription = DavCalendarSubscription::forOwner($owner)->forKey('holidays')->firstOrFail();

    expect($subscription)
        ->source->toBe('https://example.com/holidays.ics')
        ->display_name->toBe('Holidays')
        ->color->toBe('#B8255FFF')
        ->refresh_rate->toBe('P1D')
        ->order->toBe(10)
        ->strip_todos->toBeTrue()
        ->strip_alarms->toBeFalse()
        ->strip_attachments->toBeTrue();

    $response = $this->callDav('PROPFIND', $path, $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/" xmlns:ical="http://apple.com/ns/ical/">
            <d:prop>
                <d:resourcetype />
                <cs:source />
                <d:displayname />
                <ical:calendar-color />
                <ical:refreshrate />
                <ical:calendar-order />
                <cs:subscribed-strip-todos />
                <cs:subscribed-strip-alarms />
                <cs:subscribed-strip-attachments />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207);

    expect(calendarSubscriptionResourceTypes($response))->toBe([
        '{DAV:}collection',
        '{http://calendarserver.org/ns/}subscribed',
    ])
        ->and(calendarSubscriptionPropertyText($response, 'http://calendarserver.org/ns/', 'source'))->toBe('https://example.com/holidays.ics')
        ->and(calendarSubscriptionPropertyText($response, 'DAV:', 'displayname'))->toBe('Holidays')
        ->and(calendarSubscriptionPropertyText($response, 'http://apple.com/ns/ical/', 'calendar-color'))->toBe('#B8255FFF')
        ->and(calendarSubscriptionPropertyText($response, 'http://apple.com/ns/ical/', 'refreshrate'))->toBe('P1D')
        ->and(calendarSubscriptionPropertyText($response, 'http://apple.com/ns/ical/', 'calendar-order'))->toBe('10');

    expect(calendarSubscriptionPropertyStatuses($response))->toMatchArray([
        '{http://calendarserver.org/ns/}subscribed-strip-todos' => 200,
        '{http://calendarserver.org/ns/}subscribed-strip-alarms' => 404,
        '{http://calendarserver.org/ns/}subscribed-strip-attachments' => 200,
    ]);

    $this->callDav('PROPPATCH', $path, $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propertyupdate xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/" xmlns:ical="http://apple.com/ns/ical/">
            <d:set>
                <d:prop>
                    <cs:source>
                        <d:href>https://example.com/updated.ics</d:href>
                    </cs:source>
                    <d:displayname>Team Holidays</d:displayname>
                    <ical:calendar-color>#00AA00FF</ical:calendar-color>
                    <ical:refreshrate>PT4H</ical:refreshrate>
                    <ical:calendar-order>20</ical:calendar-order>
                    <cs:subscribed-strip-alarms>1</cs:subscribed-strip-alarms>
                </d:prop>
            </d:set>
            <d:remove>
                <d:prop>
                    <cs:subscribed-strip-todos />
                    <cs:subscribed-strip-attachments />
                </d:prop>
            </d:remove>
        </d:propertyupdate>
        XML)
        ->assertStatus(207);

    $subscription->refresh();

    expect($subscription)
        ->source->toBe('https://example.com/updated.ics')
        ->display_name->toBe('Team Holidays')
        ->color->toBe('#00AA00FF')
        ->refresh_rate->toBe('PT4H')
        ->order->toBe(20)
        ->strip_todos->toBeFalse()
        ->strip_alarms->toBeTrue()
        ->strip_attachments->toBeFalse();

    DavCalendar::factory()->withInstance([
        'uri' => 'personal',
    ])->create([
        'owner_id' => $owner->getKey(),
    ]);

    $this->callDav('DELETE', $path, $actor['header'])
        ->assertNoContent();

    expect($subscription->fresh())->toBeNull()
        ->and(DavCalendar::query()->where('owner_id', $owner->getKey())->exists())->toBeTrue();

    $this->callDav('PROPFIND', $path, $actor['header'])
        ->assertNotFound();
});
