<?php

use Bambamboole\LaravelDav\Tests\Integration\Support\CalDavTester;
use Bambamboole\LaravelDav\Tests\Integration\Support\CaldavTesterResult;

it('captures the caldav-server-tester compatibility status quo', function (): void {
    $result = CalDavTester::runCompatibilityTests();

    expect($result)->toBeInstanceOf(CaldavTesterResult::class);

    expect($result->erroredChecks)->toBe([]);

    // Every caldav-server-tester compatibility check now grades as fully
    // supported, so the tester reports no deviations. Server-side `<C:expand>`
    // of recurring VTODOs was the last remaining gap.
    expect($result->featureNames())->toBe([]);
});
