<?php

namespace Bambamboole\LaravelDav\Tests\Integration\Support;

use RuntimeException;

/**
 * The support levels caldav-server-tester reports for a feature, ordered from
 * best to worst.
 */
enum SupportLevel: string
{
    case Full = 'full';
    case Quirk = 'quirk';
    case Fragile = 'fragile';
    case Ungraceful = 'ungraceful';
    case Broken = 'broken';
    case Unsupported = 'unsupported';
    case Unknown = 'unknown';

    public static function fromTester(string $value): self
    {
        return self::tryFrom($value) ?? throw new RuntimeException(
            "Unknown caldav-server-tester support level '{$value}'. Add it to ".self::class.'.'
        );
    }
}
