<?php

namespace Bambamboole\LaravelDav\Tests\Integration\Support;

/**
 * Shared, deterministic fixture values used by both the seeder (which runs in
 * the spawned testbench process) and the orchestrating test (which authenticates
 * the external caldav-server-tester against the booted server).
 *
 * Two principals are seeded so the tester can run its multi-user scheduling
 * checks (RFC 6638 auto-schedule, inbox delivery, free/busy) — those grade
 * "unknown" with a single account because they require a second calendar user.
 */
final class CaldavTesterFixture
{
    public const USERNAME = 'tester';

    public const SECRET = 'tester-secret';

    public const OWNER_NAME = 'CalDAV Tester';

    public const OWNER_EMAIL = 'tester@example.com';

    public const SECOND_USERNAME = 'tester-2';

    public const SECOND_OWNER_NAME = 'CalDAV Tester Two';

    public const SECOND_OWNER_EMAIL = 'tester-2@example.com';

    public const CALENDAR_URI = 'personal';

    public const CALENDAR_DISPLAY_NAME = 'Test Calendar';

    public const ADDRESS_BOOK_URI = 'personal';

    public const ADDRESS_BOOK_DISPLAY_NAME = 'Test Contacts';

    public const PRIMARY_SECTION = 'primary';

    public const SECONDARY_SECTION = 'secondary';
}
