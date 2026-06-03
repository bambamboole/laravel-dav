<?php

use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Models\DavCard;
use Bambamboole\LaravelDav\Models\DavCredential;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;
use Illuminate\Support\Facades\Hash;

/**
 * @return array{owner: OwnerUser, username: string, secret: string, header: string}
 */
function davActor(): array
{
    $owner = OwnerUser::factory()->create();
    $secret = 'super-secret-token';
    $username = 'dav-'.$owner->getKey();

    DavCredential::factory()->create([
        'user_id' => $owner->getKey(),
        'username' => $username,
        'secret_hash' => Hash::make($secret),
    ]);

    return [
        'owner' => $owner,
        'username' => $username,
        'secret' => $secret,
        'header' => davAuthHeader($username, $secret),
    ];
}

it('redirects well known caldav and carddav discovery to the dav root', function (string $path): void {
    $this->call('PROPFIND', $path)->assertRedirect('/dav/');
})->with([
    'caldav' => '/.well-known/caldav',
    'carddav' => '/.well-known/carddav',
]);

it('challenges unauthenticated dav requests', function (): void {
    $this->call('PROPFIND', '/dav/')
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Basic realm="'.config('dav.realm').'", charset="UTF-8"');
});

it('serves the dav root for authenticated requests', function (): void {
    $actor = davActor();

    $this->call('PROPFIND', '/dav/', [], [], [], ['HTTP_AUTHORIZATION' => $actor['header']])
        ->assertStatus(207)
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('/dav/principals/', false);
});

it('rejects a wrong secret', function (): void {
    $actor = davActor();

    $this->call('PROPFIND', '/dav/', [], [], [], [
        'HTTP_AUTHORIZATION' => davAuthHeader($actor['username'], 'wrong'),
    ])->assertUnauthorized();
});

it('returns the principal with a displayname', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    $this->call('PROPFIND', '/dav/principals/'.$owner->getKey().'/', [], [], [], [
        'CONTENT_TYPE' => 'application/xml',
        'HTTP_AUTHORIZATION' => $actor['header'],
        'HTTP_DEPTH' => '0',
    ], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:displayname />
            </d:prop>
        </d:propfind>
        XML)
        ->assertStatus(207)
        ->assertSee('/dav/principals/'.$owner->getKey().'/', false)
        ->assertSee($owner->name, false);
});

it('puts, fetches, lists and deletes a calendar object through caldav', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $payload = ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:event-1
        DTSTAMP:20260603T000000Z
        SUMMARY:Deep Work
        DTSTART:20260603T070000Z
        DTEND:20260603T083000Z
        END:VEVENT
        END:VCALENDAR
        ICS);

    $path = '/dav/calendars/'.$owner->getKey().'/personal/event-1.ics';

    davPut($this, $path, $actor['header'], $payload, 'text/calendar')->assertSuccessful();

    expect(DavCalendarObject::query()->where('uri', 'event-1.ics')->first())
        ->not->toBeNull()
        ->uid->toBe('event-1')
        ->calendar_data->toBe($payload);

    $this->withHeaders(['Authorization' => $actor['header']])
        ->get($path)
        ->assertSuccessful()
        ->assertContent($payload);

    $this->call('PROPFIND', '/dav/calendars/'.$owner->getKey().'/personal/', [], [], [], [
        'HTTP_AUTHORIZATION' => $actor['header'],
        'HTTP_DEPTH' => '1',
    ])
        ->assertStatus(207)
        ->assertSee('event-1.ics', false);

    $this->withHeaders(['Authorization' => $actor['header']])
        ->delete($path)
        ->assertSuccessful();

    $this->withHeaders(['Authorization' => $actor['header']])
        ->get($path)
        ->assertNotFound();
});

it('puts, fetches and deletes a contact card through carddav', function (): void {
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

it('reports calendar changes through webdav sync', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $payload = ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VEVENT
        UID:event-1
        DTSTAMP:20260603T000000Z
        SUMMARY:Deep Work
        DTSTART:20260603T070000Z
        DTEND:20260603T083000Z
        END:VEVENT
        END:VCALENDAR
        ICS);

    davPut(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/event-1.ics',
        $actor['header'],
        $payload,
        'text/calendar',
    )->assertSuccessful();

    davSyncReport(
        $this,
        '/dav/calendars/'.$owner->getKey().'/personal/',
        $actor['header'],
        'http://sabredav.org/ns/sync/1',
    )
        ->assertSuccessful()
        ->assertSee('event-1.ics', false)
        ->assertSee('sync-token', false)
        ->assertSee('http://sabredav.org/ns/sync/', false);
});

it('persists a custom property through proppatch and reads it back', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $path = '/dav/calendars/'.$owner->getKey().'/personal/';

    $this->call('PROPPATCH', $path, [], [], [], [
        'CONTENT_TYPE' => 'application/xml',
        'HTTP_AUTHORIZATION' => $actor['header'],
    ], <<<'XML'
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

    $this->call('PROPFIND', $path, [], [], [], [
        'CONTENT_TYPE' => 'application/xml',
        'HTTP_AUTHORIZATION' => $actor['header'],
        'HTTP_DEPTH' => '0',
    ], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:" xmlns:x="http://life-os.test/ns">
            <d:prop>
                <x:custom-flag />
            </d:prop>
        </d:propfind>
        XML)
        ->assertStatus(207)
        ->assertSee('enabled', false);
});
