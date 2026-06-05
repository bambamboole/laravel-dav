<?php

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarAttachment;
use Bambamboole\LaravelDav\Models\DavCalendarInstance;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Sabre\DAV\Sharing\Plugin as SharingPlugin;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;

beforeEach(function (): void {
    Storage::fake('dav-attachments');

    config([
        'dav.attachments.disk' => 'dav-attachments',
        'dav.attachments.path' => 'managed',
    ]);
});

/**
 * @return array{path: string, payload: string}
 */
function rfc8607Event(mixed $test, array $actor, string $uid = 'managed-attachment-event'): array
{
    DavCalendar::factory()->withInstance(['uri' => 'personal'])->create(['owner_id' => $actor['owner']->getKey()]);

    $path = '/dav/calendars/'.$actor['owner']->getKey().'/personal/'.$uid.'.ics';
    $payload = calendarObjectPayload('VEVENT', [
        'UID' => $uid,
        'SUMMARY' => 'Managed attachment meeting',
        'DTSTAMP' => '20260603T000000Z',
        'DTSTART' => '20260603T090000Z',
        'DTEND' => '20260603T100000Z',
    ]);

    davPut($test, $path, $actor['header'], $payload, 'text/calendar')->assertCreated();

    return ['path' => $path, 'payload' => $payload];
}

function rfc8607SchedulingEvent(mixed $test, array $actor): string
{
    DavCalendar::factory()->withInstance(['uri' => 'personal'])->create(['owner_id' => $actor['owner']->getKey()]);

    $path = '/dav/calendars/'.$actor['owner']->getKey().'/personal/attendee-copy.ics';
    $payload = ical(<<<ICS
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//LaravelDav//Tests//EN
        BEGIN:VEVENT
        UID:attendee-copy
        DTSTAMP:20260603T000000Z
        DTSTART:20260603T090000Z
        DTEND:20260603T100000Z
        ORGANIZER:mailto:organizer@example.com
        ATTENDEE:mailto:{$actor['owner']->getDavPrincipalEmail()}
        SUMMARY:Attendee copy
        END:VEVENT
        END:VCALENDAR
        ICS);

    davPut($test, $path, $actor['header'], $payload, 'text/calendar')->assertCreated();

    return $path;
}

function rfc8607PostAttachment(mixed $test, string $path, string $authHeader, string $body, string $filename = 'agenda.txt', string $contentType = 'text/plain'): TestResponse
{
    return $test->callDav('POST', $path.'?action=attachment-add', $authHeader, $body, [
        'HTTP_CONTENT_DISPOSITION' => 'attachment; filename="'.$filename.'"',
    ], $contentType);
}

function rfc8607ManagedId(TestResponse $response): string
{
    $managedId = $response->headers->get('Cal-Managed-ID');

    expect($managedId)->toBeString()->not->toBe('');

    return (string) $managedId;
}

function rfc8607CalendarObject(string $uri): DavCalendarObject
{
    return DavCalendarObject::query()->where('uri', basename($uri))->firstOrFail();
}

function rfc8607AttachmentProperty(string $payload, string $managedId): Property
{
    $calendar = Reader::read($payload);

    expect($calendar)->toBeInstanceOf(VCalendar::class);

    try {
        foreach ($calendar->getBaseComponents() as $component) {
            foreach ($component->select('ATTACH') as $attach) {
                if ($attach instanceof Property && (string) $attach['MANAGED-ID'] === $managedId) {
                    return clone $attach;
                }
            }
        }
    } finally {
        $calendar->destroy();
    }

    throw new RuntimeException('Managed attachment property was not found.');
}

function rfc8607AttachmentPath(Property $attach): string
{
    $path = parse_url($attach->getValue(), PHP_URL_PATH);

    expect($path)->toBeString()->not->toBe('');

    return (string) $path;
}

/**
 * @see https://www.rfc-editor.org/rfc/rfc8607.html#section-3.2
 * @see https://www.rfc-editor.org/rfc/rfc8607.html#section-3.4
 * @see https://www.rfc-editor.org/rfc/rfc8607.html#section-4.3
 * @see https://www.rfc-editor.org/rfc/rfc8607.html#section-5.1
 */
it('[sections 3.2, 3.4, 4.3, and 5.1] adds managed attachments and rewrites calendar data', function (): void {
    $actor = davActor();
    $event = rfc8607Event($this, $actor);

    $options = $this->callDav('OPTIONS', '/dav/calendars/'.$actor['owner']->getKey().'/', $actor['header'])
        ->assertSuccessful();

    expect($options->headers->get('DAV'))->toContain('calendar-managed-attachments');

    $managedId = rfc8607ManagedId(
        rfc8607PostAttachment($this, $event['path'], $actor['header'], 'Meeting notes')->assertSuccessful()
    );

    $attachment = DavCalendarAttachment::query()->where('managed_id', $managedId)->firstOrFail();
    $calendarObject = rfc8607CalendarObject($event['path']);
    $attach = rfc8607AttachmentProperty($calendarObject->calendar_data, $managedId);

    expect($attachment)
        ->dav_calendar_object_id->toBe($calendarObject->id)
        ->created_by_owner_id->toBe($actor['owner']->getKey())
        ->filename->toBe('agenda.txt')
        ->content_type->toBe('text/plain')
        ->size->toBe(strlen('Meeting notes'))
        ->and((string) $attach['MANAGED-ID'])->toBe($managedId)
        ->and((string) $attach['FMTTYPE'])->toBe('text/plain')
        ->and((string) $attach['FILENAME'])->toBe('agenda.txt')
        ->and((string) $attach['SIZE'])->toBe((string) strlen('Meeting notes'));

    expect(rfc8607AttachmentPath($attach))->toBe('/dav/attachments/'.$managedId.'/agenda.txt');
    Storage::disk('dav-attachments')->assertExists($attachment->storage_path);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc8607.html#section-3.5
 * @see https://www.rfc-editor.org/rfc/rfc8607.html#section-3.6
 * @see https://www.rfc-editor.org/rfc/rfc8607.html#section-5.1
 */
it('[sections 3.5, 3.6, and 5.1] updates and removes managed attachments through POST actions', function (): void {
    $actor = davActor();
    $event = rfc8607Event($this, $actor);
    $oldManagedId = rfc8607ManagedId(rfc8607PostAttachment($this, $event['path'], $actor['header'], 'First version')->assertSuccessful());
    $oldAttachment = DavCalendarAttachment::query()->where('managed_id', $oldManagedId)->firstOrFail();

    $updateResponse = $this->callDav('POST', $event['path'].'?action=attachment-update&managed-id='.$oldManagedId, $actor['header'], 'Updated version', [
        'HTTP_CONTENT_DISPOSITION' => 'attachment; filename="updated.txt"',
    ], 'text/plain')->assertSuccessful();

    $newManagedId = rfc8607ManagedId($updateResponse);
    $calendarObject = rfc8607CalendarObject($event['path']);

    expect($newManagedId)->not->toBe($oldManagedId)
        ->and(DavCalendarAttachment::query()->where('managed_id', $oldManagedId)->exists())->toBeFalse()
        ->and(DavCalendarAttachment::query()->where('managed_id', $newManagedId)->exists())->toBeTrue()
        ->and($calendarObject->calendar_data)->not->toContain($oldManagedId)
        ->and($calendarObject->calendar_data)->toContain($newManagedId);

    Storage::disk('dav-attachments')->assertMissing($oldAttachment->storage_path);

    $this->callDav('POST', $event['path'].'?action=attachment-remove&managed-id='.$newManagedId, $actor['header'])
        ->assertNoContent();

    $calendarObject->refresh();

    expect(DavCalendarAttachment::query()->where('managed_id', $newManagedId)->exists())->toBeFalse()
        ->and($calendarObject->calendar_data)->not->toContain('ATTACH')
        ->and($calendarObject->calendar_data)->not->toContain($newManagedId);
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc8607.html#section-3.8
 * @see https://www.rfc-editor.org/rfc/rfc8607.html#section-3.9
 * @see https://www.rfc-editor.org/rfc/rfc8607.html#section-3.10
 * @see https://www.rfc-editor.org/rfc/rfc8607.html#section-3.12.2
 */
it('[sections 3.8, 3.9, 3.10, and 3.12.2] serves downloads through calendar object ACL and blocks direct mutation', function (): void {
    $owner = davActor();
    $reader = davActor();
    $other = davActor();
    $event = rfc8607Event($this, $owner);
    $managedId = rfc8607ManagedId(rfc8607PostAttachment($this, $event['path'], $owner['header'], 'Download body')->assertSuccessful());
    $attachPath = rfc8607AttachmentPath(rfc8607AttachmentProperty(rfc8607CalendarObject($event['path'])->calendar_data, $managedId));

    DavCalendarInstance::factory()->create([
        'dav_calendar_id' => rfc8607CalendarObject($event['path'])->dav_calendar_id,
        'owner_id' => $reader['owner']->getKey(),
        'uri' => 'shared-read',
        'access' => SharingPlugin::ACCESS_READ,
    ]);

    $download = $this->withHeaders(['Authorization' => $owner['header']])
        ->get($attachPath)
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="agenda.txt"')
        ->assertContent('Download body');

    expect($download->headers->get('Content-Type'))->toContain('text/plain');

    $this->withHeaders(['Authorization' => $reader['header']])
        ->get($attachPath)
        ->assertOk()
        ->assertContent('Download body');

    $this->withHeaders(['Authorization' => $other['header']])
        ->get($attachPath)
        ->assertForbidden();

    $this->callDav('PUT', $attachPath, $owner['header'], 'changed', contentType: 'text/plain')
        ->assertStatus(405);

    $this->withHeaders(['Authorization' => $owner['header']])
        ->delete($attachPath)
        ->assertStatus(405);

    rfc8607PostAttachment($this, '/dav/calendars/'.$reader['owner']->getKey().'/shared-read/'.basename($event['path']), $reader['header'], 'Blocked')
        ->assertForbidden();
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc8607.html#section-3.12.2
 */
it('[section 3.12.2] prevents attendees from adding updating or removing managed attachments', function (): void {
    $attendee = davActor();
    $eventPath = rfc8607SchedulingEvent($this, $attendee);

    rfc8607PostAttachment($this, $eventPath, $attendee['header'], 'Attendee notes')
        ->assertForbidden();

    expect(DavCalendarAttachment::query()->exists())->toBeFalse();
});

/**
 * @see https://www.rfc-editor.org/rfc/rfc8607.html#section-3.7
 * @see https://www.rfc-editor.org/rfc/rfc8607.html#section-3.12.2
 */
it('[sections 3.7 and 3.12.2] only lets the original creator reuse managed ids in calendar object PUTs', function (): void {
    $creator = davActor();
    $other = davActor();
    $event = rfc8607Event($this, $creator);
    $managedId = rfc8607ManagedId(rfc8607PostAttachment($this, $event['path'], $creator['header'], 'Reusable body')->assertSuccessful());
    $attach = rfc8607AttachmentProperty(rfc8607CalendarObject($event['path'])->calendar_data, $managedId);
    $attachLine = 'ATTACH;MANAGED-ID='.$managedId.';FMTTYPE=text/plain;FILENAME=agenda.txt;SIZE=13:'.$attach->getValue();

    davPut($this, '/dav/calendars/'.$creator['owner']->getKey().'/personal/reuse-ok.ics', $creator['header'], ical(<<<ICS
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//LaravelDav//Tests//EN
        BEGIN:VEVENT
        UID:reuse-ok
        DTSTAMP:20260603T000000Z
        DTSTART:20260603T110000Z
        DTEND:20260603T120000Z
        SUMMARY:Creator reuse
        {$attachLine}
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertCreated();

    DavCalendar::factory()->withInstance(['uri' => 'personal'])->create(['owner_id' => $other['owner']->getKey()]);

    davPut($this, '/dav/calendars/'.$other['owner']->getKey().'/personal/reuse-denied.ics', $other['header'], ical(<<<ICS
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//LaravelDav//Tests//EN
        BEGIN:VEVENT
        UID:reuse-denied
        DTSTAMP:20260603T000000Z
        DTSTART:20260603T130000Z
        DTEND:20260603T140000Z
        SUMMARY:Other reuse
        {$attachLine}
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertForbidden();
});
