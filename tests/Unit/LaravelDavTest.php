<?php

use Bambamboole\LaravelDav\Facades\Dav;

it('builds the sabre base uri from the configured route prefix', function (string $prefix, string $baseUri): void {
    config()->set('dav.route.prefix', $prefix);

    expect(Dav::baseUri())->toBe($baseUri);
})->with([
    'default path' => ['dav', '/dav/'],
    'nested path' => ['remote.php/dav', '/remote.php/dav/'],
    'surrounding slashes' => ['/remote.php/dav/', '/remote.php/dav/'],
    'root path' => ['', '/'],
]);
