<?php

use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Illuminate\Testing\TestResponse;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;
use Sabre\VObject\Splitter\VCard as VCardSplitter;

function exportPluginCalendar(TestResponse $response): VCalendar
{
    $calendar = Reader::read($response->getContent());

    if (! $calendar instanceof VCalendar) {
        throw new RuntimeException('The ICS export response did not contain a vCalendar.');
    }

    return $calendar;
}

/**
 * @return array<string, VCard>
 */
function exportPluginCards(TestResponse $response): array
{
    $stream = fopen('php://temp', 'r+');

    if ($stream === false) {
        throw new RuntimeException('Unable to open an in-memory stream for the VCF export response.');
    }

    fwrite($stream, $response->getContent());
    rewind($stream);

    $splitter = new VCardSplitter($stream);
    $cards = [];

    try {
        while ($card = $splitter->getNext()) {
            if (! $card instanceof VCard) {
                throw new RuntimeException('The VCF export response contained a non-vCard object.');
            }

            $cards[(string) $card->UID] = $card;
        }
    } finally {
        fclose($stream);
    }

    ksort($cards);

    return $cards;
}

/**
 * @see http://sabre.io/dav/ics-export-plugin/
 */
it('exports a calendar collection as merged iCalendar data through the ICS export plugin', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavCalendar::factory()->create([
        'user_id' => $owner->getKey(),
        'uri' => 'personal',
        'display_name' => 'Personal',
    ]);

    $basePath = '/dav/calendars/'.$owner->getKey().'/personal/';

    davPut($this, $basePath.'event-1.ics', $actor['header'], calendarObjectPayload('VEVENT', [
        'UID' => 'event-1',
        'DTSTAMP' => '20260603T000000Z',
        'SUMMARY' => 'Deep Work',
        'DTSTART' => '20260603T070000Z',
        'DTEND' => '20260603T083000Z',
    ]), 'text/calendar')->assertSuccessful();

    davPut($this, $basePath.'task-1.ics', $actor['header'], calendarObjectPayload('VTODO', [
        'UID' => 'task-1',
        'DTSTAMP' => '20260603T000000Z',
        'SUMMARY' => 'Follow up',
        'DUE' => '20260604T090000Z',
    ]), 'text/calendar')->assertSuccessful();

    $response = $this->callDav('GET', $basePath.'?export', $actor['header'])
        ->assertOk()
        ->assertHeaderContains('Content-Type', 'text/calendar')
        ->assertHeaderContains('Content-Disposition', 'attachment; filename="personal-');

    $calendar = exportPluginCalendar($response);

    try {
        $events = $calendar->select('VEVENT');
        $tasks = $calendar->select('VTODO');

        expect((string) $calendar->{'X-WR-CALNAME'})->toBe('Personal')
            ->and($events)->toHaveCount(1)
            ->and((string) $events[0]->UID)->toBe('event-1')
            ->and((string) $events[0]->SUMMARY)->toBe('Deep Work')
            ->and($tasks)->toHaveCount(1)
            ->and((string) $tasks[0]->UID)->toBe('task-1')
            ->and((string) $tasks[0]->SUMMARY)->toBe('Follow up');
    } finally {
        $calendar->destroy();
    }
});

/**
 * @see http://sabre.io/dav/vcf-export-plugin/
 */
it('exports an address book collection as vCard data through the VCF export plugin', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavAddressBook::factory()->create([
        'user_id' => $owner->getKey(),
        'uri' => 'personal',
    ]);

    $basePath = '/dav/addressbooks/'.$owner->getKey().'/personal/';

    davPut($this, $basePath.'ada.vcf', $actor['header'], contactCardPayload([
        'UID' => 'contact-1',
        'FN' => 'Ada Lovelace',
        'N' => ['value' => 'Lovelace;Ada;;;'],
        'EMAIL' => 'ada@example.com',
    ]), 'text/vcard')->assertSuccessful();

    davPut($this, $basePath.'grace.vcf', $actor['header'], contactCardPayload([
        'UID' => 'contact-2',
        'FN' => 'Grace Hopper',
        'N' => ['value' => 'Hopper;Grace;;;'],
        'EMAIL' => 'grace@example.com',
    ]), 'text/vcard')->assertSuccessful();

    $response = $this->callDav('GET', $basePath.'?export', $actor['header'])
        ->assertOk()
        ->assertHeaderContains('Content-Type', 'text/directory')
        ->assertHeaderContains('Content-Disposition', 'attachment; filename="personal-');

    $cards = exportPluginCards($response);

    try {
        expect(array_keys($cards))->toBe(['contact-1', 'contact-2'])
            ->and((string) $cards['contact-1']->FN)->toBe('Ada Lovelace')
            ->and((string) $cards['contact-1']->EMAIL)->toBe('ada@example.com')
            ->and((string) $cards['contact-2']->FN)->toBe('Grace Hopper')
            ->and((string) $cards['contact-2']->EMAIL)->toBe('grace@example.com');
    } finally {
        foreach ($cards as $card) {
            $card->destroy();
        }
    }
});
