<?php

namespace Bambamboole\LaravelDav\Tests\Integration\Support;

/**
 * Shared, deterministic fixture values used by both the seeder (which runs in
 * the spawned testbench process) and the orchestrating test (which authenticates
 * the external caldav-server-tester against the booted server).
 */
final class CaldavTesterFixture
{
    public const USERNAME = 'tester';

    public const SECRET = 'tester-secret';

    public const OWNER_NAME = 'CalDAV Tester';

    public const OWNER_EMAIL = 'tester@example.com';

    public const CALENDAR_URI = 'personal';

    public const CALENDAR_DISPLAY_NAME = 'Test Calendar';

    public const ADDRESS_BOOK_URI = 'personal';

    public const ADDRESS_BOOK_DISPLAY_NAME = 'Test Contacts';
}
