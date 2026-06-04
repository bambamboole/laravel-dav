<?php

use Bambamboole\LaravelDav\Tests\Integration\Support\CalDavTester;
use Bambamboole\LaravelDav\Tests\Integration\Support\CaldavTesterResult;
use Bambamboole\LaravelDav\Tests\Integration\Support\SupportLevel;

it('captures the caldav-server-tester compatibility status quo', function (): void {
    $result = CalDavTester::runCompatibilityTests();

    expect($result)->toBeInstanceOf(CaldavTesterResult::class);

    expect($result->erroredChecks)->toBe([]);

    expect($result->featureNames())->toBe([
        'save-load.event.timezone',
        'scheduling',
        'search.recurrences.expanded.todo',
    ]);

    expect($result->support('save-load.event.timezone'))->toBe(SupportLevel::Broken);
    expect($result->support('scheduling'))->toBe(SupportLevel::Unsupported);
    expect($result->support('search.recurrences.expanded.todo'))->toBe(SupportLevel::Unsupported);
});
