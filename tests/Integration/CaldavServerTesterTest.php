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

    // CheckRecurrenceSearch no longer crashes — all checks complete cleanly.
    expect($result->erroredChecks)->toBe([]);

    // The complete set of graded features, so a newly reported or dropped
    // feature surfaces here instead of passing silently.
    // search.recurrences.* are now full after the recurrence fix and therefore
    // drop out of the deviation list — except search.recurrences.expanded.todo
    // which remains unsupported (server-side VTODO expansion; tracked in todo #48).
    expect($result->featureNames())->toBe([
        'save-load.event.timezone',
        'scheduling',
        'search.recurrences.expanded.todo',
    ]);

    // Per-feature status quo.
    expect($result->support('save-load.event.timezone'))->toBe(SupportLevel::Broken);
    expect($result->support('scheduling'))->toBe(SupportLevel::Unsupported);
    // Known limitation: server-side VTODO recurrence expansion is not implemented (todo #48).
    expect($result->support('search.recurrences.expanded.todo'))->toBe(SupportLevel::Unsupported);
});
