<?php

use Bambamboole\LaravelDav\Mail\SchedulingMessageMail;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavSchedulingObject;
use Bambamboole\LaravelDav\Tests\TestCase;
use Illuminate\Support\Facades\Mail;

function invitePut(TestCase $test, array $organizer, string $uri, string $body): void
{
    davPut($test, '/dav/calendars/'.$organizer['owner']->getKey().'/personal/'.$uri, $organizer['header'], $body, 'text/calendar')
        ->assertSuccessful();
}

function externalInvite(string $organizerEmail, string $attendeeEmail): string
{
    return ical(<<<ICS
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Tests//EN
        BEGIN:VEVENT
        UID:imip-event
        DTSTAMP:20260101T000000Z
        DTSTART:20260601T090000Z
        DTEND:20260601T100000Z
        ORGANIZER:mailto:{$organizerEmail}
        ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:{$attendeeEmail}
        SUMMARY:Project kickoff
        END:VEVENT
        END:VCALENDAR
        ICS);
}

it('[section 4] emails an iTip REQUEST to an external attendee when iMIP is enabled', function (): void {
    config(['dav.scheduling.from' => 'no-reply@dav.test']);
    Mail::fake();

    $organizer = davActor();
    DavCalendar::factory()->create(['user_id' => $organizer['owner']->getKey(), 'uri' => 'personal']);

    invitePut($this, $organizer, 'imip-event.ics', externalInvite($organizer['owner']->getDavPrincipalEmail(), 'external@elsewhere.test'));

    Mail::assertSent(SchedulingMessageMail::class, function (SchedulingMessageMail $mail): bool {
        return $mail->hasTo('external@elsewhere.test')
            && $mail->method === 'REQUEST'
            && str_contains($mail->subjectLine, 'Invitation:')
            && str_contains($mail->calendarBody, 'METHOD:REQUEST');
    });
});

it('[section 4] emails an iTip CANCEL to an external attendee when the organizer deletes the event', function (): void {
    config(['dav.scheduling.from' => 'no-reply@dav.test']);
    Mail::fake();

    $organizer = davActor();
    DavCalendar::factory()->create(['user_id' => $organizer['owner']->getKey(), 'uri' => 'personal']);

    invitePut($this, $organizer, 'imip-event.ics', externalInvite($organizer['owner']->getDavPrincipalEmail(), 'external@elsewhere.test'));

    $this->withHeaders(['Authorization' => $organizer['header']])
        ->delete('/dav/calendars/'.$organizer['owner']->getKey().'/personal/imip-event.ics')
        ->assertSuccessful();

    Mail::assertSent(SchedulingMessageMail::class, function (SchedulingMessageMail $mail): bool {
        return $mail->hasTo('external@elsewhere.test') && $mail->method === 'CANCEL';
    });
});

it('does not schedule or email when scheduling is disabled', function (): void {
    config(['dav.scheduling.enabled' => false]);
    Mail::fake();

    $organizer = davActor();
    DavCalendar::factory()->create(['user_id' => $organizer['owner']->getKey(), 'uri' => 'personal']);

    invitePut($this, $organizer, 'imip-event.ics', externalInvite($organizer['owner']->getDavPrincipalEmail(), 'external@elsewhere.test'));

    Mail::assertNothingSent();
});

it('does not email a local attendee — it is delivered to their inbox instead', function (): void {
    config(['dav.scheduling.from' => 'no-reply@dav.test']);
    Mail::fake();

    $organizer = davActor();
    $attendee = davActor();
    DavCalendar::factory()->create(['user_id' => $organizer['owner']->getKey(), 'uri' => 'personal']);
    DavCalendar::factory()->create(['user_id' => $attendee['owner']->getKey(), 'uri' => 'personal']);

    invitePut($this, $organizer, 'imip-event.ics', externalInvite(
        $organizer['owner']->getDavPrincipalEmail(),
        $attendee['owner']->getDavPrincipalEmail(),
    ));

    Mail::assertNothingSent();
    expect(DavSchedulingObject::query()->where('user_id', $attendee['owner']->getKey())->count())->toBeGreaterThan(0);
});
