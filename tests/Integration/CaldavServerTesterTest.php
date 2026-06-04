<?php

use Bambamboole\LaravelDav\Tests\Integration\Support\CaldavTesterHarness;

/**
 * Boots the DAV server as a real HTTP process, runs the external python
 * caldav-server-tester against it, and asserts the parsed feature/support map
 * matches the committed status-quo baseline.
 *
 * This test intentionally does NOT use the package TestCase (no in-memory
 * RefreshDatabase): the tester is a separate OS process that needs a real,
 * network-reachable server backed by a shared file database.
 *
 * Regenerate the baseline after intentionally changing server behaviour:
 *   DAV_TESTER_UPDATE_BASELINE=1 vendor/bin/pest --filter="compatibility status quo"
 */
it('captures the caldav-server-tester compatibility status quo', function (): void {
    $harness = new CaldavTesterHarness;

    try {
        $harness->boot();
        $result = $harness->runCompatibilityChecks();
    } finally {
        $harness->shutdown();
    }

    expect($result['features'])->toBeArray()->not->toBeEmpty();

    $baselinePath = __DIR__.'/baseline/caldav-server-tester.json';

    if (filter_var(getenv('DAV_TESTER_UPDATE_BASELINE'), FILTER_VALIDATE_BOOL)) {
        if (! is_dir(dirname($baselinePath))) {
            mkdir(dirname($baselinePath), 0o755, true);
        }

        file_put_contents(
            $baselinePath,
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
        );

        return;
    }

    expect(file_exists($baselinePath))->toBeTrue(
        'Baseline missing. Generate it with: DAV_TESTER_UPDATE_BASELINE=1 vendor/bin/pest --filter="compatibility status quo"',
    );

    /** @var array{features: array<string, mixed>, errored_checks: list<string>} $baseline */
    $baseline = json_decode((string) file_get_contents($baselinePath), true, 512, JSON_THROW_ON_ERROR);

    expect($result)->toEqual($baseline);
});
