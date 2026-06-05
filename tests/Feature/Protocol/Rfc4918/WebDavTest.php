<?php

use Bambamboole\LaravelDav\Models\DavCalendar;

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
