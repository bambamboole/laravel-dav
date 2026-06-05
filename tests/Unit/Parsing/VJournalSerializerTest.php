<?php

use Bambamboole\LaravelDav\Parsing\CalendarObjectParser;
use Bambamboole\LaravelDav\Parsing\CalendarObjectSerializer;

it('merge preserves the VJOURNAL component type and adds no end property', function (): void {
    $payload = ical(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Life OS//Tests//EN
        BEGIN:VJOURNAL
        UID:journal-merge-2
        DTSTAMP:20260603T000000Z
        SUMMARY:Original notes
        DTSTART:20260603T000000Z
        DESCRIPTION:Some thoughts.
        END:VJOURNAL
        END:VCALENDAR
        ICS);

    $parser = new CalendarObjectParser;
    $serializer = new CalendarObjectSerializer;

    $parsed = $parser->parse($payload, 'journal-merge-2.ics');

    $result = $serializer->merge($payload, $parsed);
    $reparsed = $parser->parse($result, 'journal-merge-2.ics');

    expect($reparsed->componentType)->toBe('VJOURNAL')
        ->and($result)->not->toContain('DTEND')
        ->and($result)->not->toContain(':DUE');
});
