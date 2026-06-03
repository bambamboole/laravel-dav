<?php

it('boots the service provider and merges config', function () {
    expect(config('dav.principal_prefix'))->toBe('principals');
});
