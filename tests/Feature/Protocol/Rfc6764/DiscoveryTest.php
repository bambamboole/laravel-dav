<?php

it('[section 5] redirects well-known CalDAV and CardDAV discovery to the DAV root', function (string $path): void {
    $this->callDav('PROPFIND', $path)->assertRedirect('/dav/');
})->with([
    'caldav' => '/.well-known/caldav',
    'carddav' => '/.well-known/carddav',
]);
