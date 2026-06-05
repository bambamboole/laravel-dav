<?php

namespace Bambamboole\LaravelDav\Sabre\CalDav;

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Parsing\CalendarObjectParser;

class UpsertCalendarObject
{
    public function __construct(
        private CalendarObjectParser $parser,
    ) {}

    public function handle(DavCalendar $calendar, string $uri, string $payload): DavCalendarObject
    {
        $parsed = $this->parser->parse($payload, $uri);
        $object = $calendar->objects()->firstOrNew(['uri' => $uri]);

        $object->updateFromData($parsed, $payload, defaultComponentType: null);

        return $object->refresh();
    }
}
