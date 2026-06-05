<?php

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavSchedulingObject;

/**
 * Two principals, each with a calendar, used to exercise local iTip delivery.
 *
 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
 */
function schedulingActors(): array
{
    $organizer = davActor();
    $attendee = davActor();

    DavCalendar::factory()->create(['user_id' => $organizer['owner']->getKey(), 'uri' => 'personal']);
    DavCalendar::factory()->create(['user_id' => $attendee['owner']->getKey(), 'uri' => 'personal']);

    return [$organizer, $attendee];
}

/**
 * @return list<string>
 */
function inboxPayloads(int|string $ownerId): array
{
    return DavSchedulingObject::query()
        ->where('user_id', $ownerId)
        ->orderBy('id')
        ->pluck('calendar_data')
        ->all();
}

it('[section 3.2.2] delivers an iTip REQUEST to a local attendee inbox when an event is scheduled', function (): void {
    [$organizer, $attendee] = schedulingActors();
    $aEmail = $organizer['owner']->getDavPrincipalEmail();
    $bEmail = $attendee['owner']->getDavPrincipalEmail();

    davPut($this, '/dav/calendars/'.$organizer['owner']->getKey().'/personal/meeting.ics', $organizer['header'], ical(<<<ICS
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Tests//EN
        BEGIN:VEVENT
        UID:meeting
        DTSTAMP:20260101T000000Z
        DTSTART:20260601T090000Z
        DTEND:20260601T100000Z
        ORGANIZER:mailto:{$aEmail}
        ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:{$bEmail}
        SUMMARY:Kickoff
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    $messages = inboxPayloads($attendee['owner']->getKey());

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toContain('METHOD:REQUEST')
        ->and($messages[0])->toContain('UID:meeting')
        ->and(inboxPayloads($organizer['owner']->getKey()))->toBe([]);
});

it('[section 3.2.3] delivers an iTip REPLY to the organizer when an attendee updates participation status', function (): void {
    [$organizer, $attendee] = schedulingActors();
    $aEmail = $organizer['owner']->getDavPrincipalEmail();
    $bEmail = $attendee['owner']->getDavPrincipalEmail();
    $aId = $organizer['owner']->getKey();
    $bId = $attendee['owner']->getKey();

    davPut($this, '/dav/calendars/'.$aId.'/personal/meeting.ics', $organizer['header'], ical(<<<ICS
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Tests//EN
        BEGIN:VEVENT
        UID:meeting
        DTSTAMP:20260101T000000Z
        DTSTART:20260601T090000Z
        DTEND:20260601T100000Z
        ORGANIZER:mailto:{$aEmail}
        ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:{$bEmail}
        SUMMARY:Kickoff
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    // The attendee accepts by writing the event into their own calendar.
    davPut($this, '/dav/calendars/'.$bId.'/personal/meeting.ics', $attendee['header'], ical(<<<ICS
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Tests//EN
        BEGIN:VEVENT
        UID:meeting
        DTSTAMP:20260101T010000Z
        DTSTART:20260601T090000Z
        DTEND:20260601T100000Z
        ORGANIZER:mailto:{$aEmail}
        ATTENDEE;PARTSTAT=ACCEPTED:mailto:{$bEmail}
        SUMMARY:Kickoff
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    $messages = inboxPayloads($aId);

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toContain('METHOD:REPLY')
        ->and($messages[0])->toContain('PARTSTAT=ACCEPTED');
});

it('[section 3.2.2] delivers an iTip CANCEL to attendees when the organizer deletes the event', function (): void {
    [$organizer, $attendee] = schedulingActors();
    $aEmail = $organizer['owner']->getDavPrincipalEmail();
    $bEmail = $attendee['owner']->getDavPrincipalEmail();
    $aId = $organizer['owner']->getKey();
    $bId = $attendee['owner']->getKey();

    davPut($this, '/dav/calendars/'.$aId.'/personal/meeting.ics', $organizer['header'], ical(<<<ICS
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Tests//EN
        BEGIN:VEVENT
        UID:meeting
        DTSTAMP:20260101T000000Z
        DTSTART:20260601T090000Z
        DTEND:20260601T100000Z
        ORGANIZER:mailto:{$aEmail}
        ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:{$bEmail}
        SUMMARY:Kickoff
        END:VEVENT
        END:VCALENDAR
        ICS), 'text/calendar')->assertSuccessful();

    $this->withHeaders(['Authorization' => $organizer['header']])
        ->delete('/dav/calendars/'.$aId.'/personal/meeting.ics')
        ->assertSuccessful();

    $messages = inboxPayloads($bId);

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toContain('METHOD:REQUEST')
        ->and($messages[1])->toContain('METHOD:CANCEL');
});
