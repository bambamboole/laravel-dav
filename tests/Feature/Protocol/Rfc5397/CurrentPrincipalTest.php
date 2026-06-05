<?php

it('[section 3] returns the current user principal from the dav root', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    $this->callDav('PROPFIND', '/dav/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:current-user-principal />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207)
        ->assertSee('current-user-principal', false)
        ->assertSee('/dav/principals/'.$owner->getKey().'/', false);
});
