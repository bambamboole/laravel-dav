<?php

use Bambamboole\LaravelDav\Server\ServerFactory;

it('boots the service provider and merges config', function () {
    $davConfig = config('dav');

    expect($davConfig)->toBeArray();

    if (! is_array($davConfig)) {
        throw new RuntimeException('The dav config was not merged as an array.');
    }

    $configKeys = array_keys($davConfig);

    expect(config('dav.principal_prefix'))->toBe('principals')
        ->and(app()->bound(ServerFactory::class))->toBeTrue()
        ->and($configKeys)->not->toContain('base_uri')
        ->and($configKeys)->not->toContain('address_book_prefix')
        ->and($configKeys)->not->toContain('default_calendar_uri')
        ->and($configKeys)->not->toContain('default_address_book_uri');
});
