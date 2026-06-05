<?php

use Bambamboole\LaravelDav\Tests\Integration\Support\CalDavTester;
use Bambamboole\LaravelDav\Tests\Integration\Support\CaldavTesterResult;
use Bambamboole\LaravelDav\Tests\Integration\Support\SupportLevel;

it('captures the caldav-server-tester compatibility status quo', function (): void {
    $result = CalDavTester::runCompatibilityTests();

    expect($result)->toBeInstanceOf(CaldavTesterResult::class);

    expect($result->erroredChecks)->toBe([]);

    // With two seeded accounts the tester exercises the multi-user scheduling
    // checks, so auto-schedule, inbox delivery, free/busy, and the broader
    // scheduling/mailbox/calendar-user-address-set features all grade as fully
    // supported and drop off the deviation list. What remains:
    // - schedule-tag: sabre/dav does not implement it (tracked as a follow-up);
    // - expanded VTODO recurrence: tracked separately.
    expect($result->featureNames())->toBe([
        'scheduling.schedule-tag',
        'search.recurrences.expanded.todo',
    ]);

    expect($result->support('scheduling.schedule-tag'))->toBe(SupportLevel::Unsupported);
    expect($result->support('search.recurrences.expanded.todo'))->toBe(SupportLevel::Unsupported);
});
