<?php

namespace Bambamboole\LaravelDav\Sabre\CalDav;

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Parsing\CalendarObjectParser;
use Bambamboole\LaravelDav\Support\CalendarObjectProjection;

class UpsertCalendarObject
{
    public function __construct(
        private CalendarObjectParser $parser,
        private CalendarObjectProjection $projection,
    ) {}

    public function handle(DavCalendar $calendar, string $uri, string $payload): DavCalendarObject
    {
        $parsed = $this->parser->parse($payload, $uri);

        return $calendar->objects()->updateOrCreate(
            ['uri' => $uri],
            [
                ...$this->projection->attributesFromData($parsed, defaultComponentType: null),
                'calendar_data' => $payload,
                'last_modified_at' => now(),
            ],
        );
    }
}
