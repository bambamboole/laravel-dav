<?php

it('challenges unauthenticated dav requests', function (): void {
    $this->callDav('PROPFIND', '/dav/')
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Basic realm="'.config('dav.realm').'", charset="UTF-8"');
});

it('rejects a wrong secret', function (): void {
    $actor = davActor();

    $this->callDav('PROPFIND', '/dav/', davAuthHeader($actor['username'], 'wrong'))
        ->assertUnauthorized();
});
