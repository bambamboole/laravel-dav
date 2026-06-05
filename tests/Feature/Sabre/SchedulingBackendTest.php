<?php

use Bambamboole\LaravelDav\Sabre\CalDav\CalendarBackend;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;

function itipMessage(string $uid): string
{
    return implode("\r\n", [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Tests//EN',
        'METHOD:REQUEST',
        'BEGIN:VEVENT',
        "UID:{$uid}",
        'DTSTAMP:20260101T000000Z',
        'DTSTART:20260601T090000Z',
        'DTEND:20260601T100000Z',
        'ORGANIZER:mailto:organizer@example.com',
        'ATTENDEE;PARTSTAT=NEEDS-ACTION:mailto:attendee@example.com',
        'SUMMARY:Invite',
        'END:VEVENT',
        'END:VCALENDAR',
    ])."\r\n";
}

it('stores, reads, lists and deletes scheduling inbox objects', function (): void {
    $owner = OwnerUser::factory()->create();
    $backend = app(CalendarBackend::class);
    $principal = 'principals/'.$owner->getKey();
    $payload = itipMessage('invite-1');

    $backend->createSchedulingObject($principal, 'invite-1.ics', $payload);

    expect($backend->getSchedulingObjects($principal))->toHaveCount(1);

    $object = $backend->getSchedulingObject($principal, 'invite-1.ics');

    expect($object)->not->toBeNull()
        ->and($object['uri'])->toBe('invite-1.ics')
        ->and($object['calendardata'])->toBe($payload)
        ->and($object['etag'])->toBe('"'.sha1($payload).'"')
        ->and($object['size'])->toBe(strlen($payload));

    $backend->deleteSchedulingObject($principal, 'invite-1.ics');

    expect($backend->getSchedulingObjects($principal))->toBe([])
        ->and($backend->getSchedulingObject($principal, 'invite-1.ics'))->toBeNull();
});

it('scopes scheduling objects to their owning principal', function (): void {
    $owner = OwnerUser::factory()->create();
    $otherOwner = OwnerUser::factory()->create();
    $backend = app(CalendarBackend::class);

    $backend->createSchedulingObject('principals/'.$owner->getKey(), 'invite.ics', itipMessage('a'));

    expect($backend->getSchedulingObjects('principals/'.$owner->getKey()))->toHaveCount(1)
        ->and($backend->getSchedulingObjects('principals/'.$otherOwner->getKey()))->toBe([])
        ->and($backend->getSchedulingObject('principals/'.$otherOwner->getKey(), 'invite.ics'))->toBeNull();
});

it('replaces an existing inbox object delivered under the same uri', function (): void {
    $owner = OwnerUser::factory()->create();
    $backend = app(CalendarBackend::class);
    $principal = 'principals/'.$owner->getKey();

    $backend->createSchedulingObject($principal, 'invite.ics', itipMessage('first'));
    $backend->createSchedulingObject($principal, 'invite.ics', itipMessage('second'));

    expect($backend->getSchedulingObjects($principal))->toHaveCount(1)
        ->and($backend->getSchedulingObject($principal, 'invite.ics')['calendardata'])->toContain('UID:second');
});
