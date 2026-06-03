<?php

namespace Bambamboole\LaravelDav\Sabre\CalDav;

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Parsing\CalendarObjectParser;

class UpsertCalendarObject
{
    public function __construct(private CalendarObjectParser $parser) {}

    public function handle(DavCalendar $calendar, string $uri, string $payload): DavCalendarObject
    {
        $parsed = $this->parser->parse($payload, $uri);

        return DavCalendarObject::query()->updateOrCreate(
            ['dav_calendar_id' => $calendar->id, 'uri' => $uri],
            [
                'uid' => $parsed->uid,
                'component_type' => $parsed->componentType,
                'summary' => $parsed->summary,
                'description' => $parsed->description,
                'location' => $parsed->location,
                'status' => $parsed->status,
                'url' => $parsed->url,
                'starts_at' => $parsed->startsAt,
                'ends_at' => $parsed->endsAt,
                'is_all_day' => $parsed->isAllDay,
                'timezone' => $parsed->timezone,
                'calendar_data' => $payload,
                'last_modified_at' => now(),
            ],
        );
    }
}
