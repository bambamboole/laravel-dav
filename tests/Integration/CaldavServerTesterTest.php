<?php

use Bambamboole\LaravelDav\Tests\Integration\Support\CalDavTester;
use Bambamboole\LaravelDav\Tests\Integration\Support\CaldavTesterResult;
use Bambamboole\LaravelDav\Tests\Integration\Support\SupportLevel;

/**
 * Boots the DAV server as a real HTTP process, runs the external python
 * caldav-server-tester against it, parses its JSON output into a typed
 * {@see CaldavTesterResult} DTO, and asserts the current compatibility status
 * quo feature by feature.
 *
 * This test intentionally does NOT use the package TestCase (no in-memory
 * RefreshDatabase): the tester is a separate OS process that needs a real,
 * network-reachable server backed by a shared file database.
 *
 * Many features are unsupported today. As the server improves, update the
 * matching expectation below (e.g. from Unsupported to Full) so the diff
 * documents the progress.
 */
it('captures the caldav-server-tester compatibility status quo', function (): void {
    $result = CalDavTester::runCompatibilityTests();

    expect($result)->toBeInstanceOf(CaldavTesterResult::class);

    // Checks that currently abort the tester before they can be graded.
    expect($result->erroredChecks)->toBe(['CheckRecurrenceSearch']);

    // The complete set of graded features, so a newly reported or dropped
    // feature surfaces here instead of passing silently.
    expect($result->featureNames())->toBe([
        'save-load.event.timezone',
        'scheduling',
        'search.comp-type.optional',
        'search.is-not-defined',
        'search.is-not-defined.class',
        'search.is-not-defined.dtend',
        'search.text.case-sensitive',
        'search.time-range.alarm',
        'search.time-range.open.start.duration',
    ]);

    // Per-feature status quo.
    expect($result->support('save-load.event.timezone'))->toBe(SupportLevel::Broken);
    expect($result->support('scheduling'))->toBe(SupportLevel::Unsupported);
    expect($result->support('search.comp-type.optional'))->toBe(SupportLevel::Fragile);
    expect($result->support('search.is-not-defined'))->toBe(SupportLevel::Fragile);
    expect($result->support('search.is-not-defined.class'))->toBe(SupportLevel::Unsupported);
    expect($result->support('search.is-not-defined.dtend'))->toBe(SupportLevel::Unsupported);
    expect($result->support('search.text.case-sensitive'))->toBe(SupportLevel::Unsupported);
    expect($result->support('search.time-range.alarm'))->toBe(SupportLevel::Unsupported);
    expect($result->support('search.time-range.open.start.duration'))->toBe(SupportLevel::Broken);
});
