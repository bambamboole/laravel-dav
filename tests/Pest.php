<?php

use Bambamboole\LaravelDav\Models\DavCredential;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;
use Bambamboole\LaravelDav\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VCard;

uses(TestCase::class)->in('Feature', 'Unit', 'Smoke', 'Integration');

function vcard(string $body): string
{
    return str_replace("\n", "\r\n", trim($body))."\r\n";
}

function ical(string $body): string
{
    return str_replace("\n", "\r\n", trim($body))."\r\n";
}

function davAuthHeader(string $username, string $secret): string
{
    return 'Basic '.base64_encode($username.':'.$secret);
}

/**
 * @return array{owner: OwnerUser, username: string, secret: string, header: string}
 */
function davActor(): array
{
    $owner = OwnerUser::factory()->create();
    $secret = 'super-secret-token';
    $username = 'dav-'.$owner->getKey();

    DavCredential::factory()->create([
        'user_id' => $owner->getKey(),
        'username' => $username,
        'secret_hash' => Hash::make($secret),
    ]);

    return [
        'owner' => $owner,
        'username' => $username,
        'secret' => $secret,
        'header' => davAuthHeader($username, $secret),
    ];
}

function davPut(TestCase $test, string $path, string $authHeader, string $payload, string $contentType): TestResponse
{
    return $test->callDav('PUT', $path, $authHeader, $payload, contentType: $contentType);
}

function davSyncReport(TestCase $test, string $path, string $authHeader, string $syncToken): TestResponse
{
    $payload = <<<XML
<?xml version="1.0" encoding="utf-8" ?>
<d:sync-collection xmlns:d="DAV:">
    <d:sync-token>{$syncToken}</d:sync-token>
    <d:sync-level>1</d:sync-level>
    <d:prop>
        <d:getetag />
    </d:prop>
</d:sync-collection>
XML;

    return $test->callDav('REPORT', $path, $authHeader, $payload);
}

function davCalendarQueryReport(TestCase $test, string $path, string $authHeader, string $filter, string $calendarData = '<cal:calendar-data />'): TestResponse
{
    $payload = <<<XML
<?xml version="1.0" encoding="utf-8" ?>
<cal:calendar-query xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
    <d:prop>
        <d:getetag />
        {$calendarData}
    </d:prop>
    {$filter}
</cal:calendar-query>
XML;

    return $test->callDav('REPORT', $path, $authHeader, $payload, [
        'HTTP_DEPTH' => '1',
    ]);
}

/**
 * @param  array<string, string|array{value: string, parameters?: array<string, string>}>  $properties
 */
function calendarObjectPayload(string $componentName, array $properties): string
{
    $calendar = new VCalendar([], false);
    $calendar->add('VERSION', '2.0');
    $calendar->add('PRODID', '-//Life OS//Tests//EN');
    $component = $calendar->createComponent($componentName, [], false);

    foreach ($properties as $name => $value) {
        if (is_array($value)) {
            $component->add($name, $value['value'], $value['parameters'] ?? []);

            continue;
        }

        $component->add($name, $value);
    }

    $calendar->add($component);

    return $calendar->serialize();
}

/**
 * @param  array<string, string|array{value: string|array<int, string>, parameters?: array<string, string>}|array<int, array{value: string, parameters?: array<string, string>}>>  $properties
 */
function contactCardPayload(array $properties): string
{
    $card = new VCard([], false);
    $card->add('VERSION', '3.0');
    $card->add('PRODID', '-//Life OS//Tests//EN');

    foreach ($properties as $name => $value) {
        if (is_array($value) && array_is_list($value)) {
            foreach ($value as $property) {
                $card->add($name, $property['value'], $property['parameters'] ?? []);
            }

            continue;
        }

        if (is_array($value)) {
            $card->add($name, $value['value'], $value['parameters'] ?? []);

            continue;
        }

        $card->add($name, $value);
    }

    return $card->serialize();
}
