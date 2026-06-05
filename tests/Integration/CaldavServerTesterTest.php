<?php

use Bambamboole\LaravelDav\Tests\Integration\Support\CalDavTester;
use Bambamboole\LaravelDav\Tests\Integration\Support\CaldavTesterResult;
use Bambamboole\LaravelDav\Tests\Integration\Support\SupportLevel;

it('captures the caldav-server-tester compatibility status quo', function (): void {
    $result = CalDavTester::runCompatibilityTests();

    expect($result)->toBeInstanceOf(CaldavTesterResult::class);

    expect($result->erroredChecks)->toBe([]);

    // The RFC 6638 scheduling foundation moves `scheduling`, `scheduling.mailbox`,
    // and `scheduling.calendar-user-address-set` to full support, so they drop off
    // the deviation list. The remaining scheduling deviations are tracked follow-ups:
    // auto-schedule + inbox-delivery (#57), free/busy query (#58), and schedule-tag,
    // which sabre/dav does not implement.
    expect($result->featureNames())->toBe([
        'scheduling.auto-schedule',
        'scheduling.freebusy-query',
        'scheduling.mailbox.inbox-delivery',
        'scheduling.schedule-tag',
        'search.recurrences.expanded.todo',
    ]);

    expect($result->support('scheduling.auto-schedule'))->toBe(SupportLevel::Unknown);
    expect($result->support('scheduling.freebusy-query'))->toBe(SupportLevel::Unknown);
    expect($result->support('scheduling.mailbox.inbox-delivery'))->toBe(SupportLevel::Unknown);
    expect($result->support('scheduling.schedule-tag'))->toBe(SupportLevel::Unsupported);
    expect($result->support('search.recurrences.expanded.todo'))->toBe(SupportLevel::Unsupported);
});
