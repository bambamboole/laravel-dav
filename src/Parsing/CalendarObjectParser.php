<?php

namespace Bambamboole\LaravelDav\Parsing;

use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Sabre\VObject\Component;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Parameter;
use Sabre\VObject\Property\ICalendar\DateTime;
use Sabre\VObject\Property\ICalendar\Duration;
use Sabre\VObject\Reader;

class CalendarObjectParser
{
    public function parse(string $raw, string $uri = ''): CalendarObjectData
    {
        $vCalendar = Reader::read($raw);

        if (! $vCalendar instanceof VCalendar) {
            $vCalendar->destroy();

            return new CalendarObjectData(
                uri: $uri,
                raw: $raw,
                etag: sha1($raw),
                size: strlen($raw),
            );
        }

        $component = $this->firstCalendarComponent($vCalendar);
        $startsAtProperty = $this->dateTimeProperty($component, 'DTSTART');
        $endsAtProperty = $this->dateTimeProperty($component, 'DTEND') ?? $this->dateTimeProperty($component, 'DUE');
        $startsAt = $this->carbonFromProperty($startsAtProperty);
        $endsAt = $this->carbonFromProperty($endsAtProperty) ?? $this->impliedEventEnd($component, $startsAt, $startsAtProperty);
        $isAllDay = $this->isAllDay($startsAtProperty) || $this->isAllDay($endsAtProperty);
        $isRecurring = $component !== null && (isset($component->RRULE) || isset($component->RDATE));

        try {
            return new CalendarObjectData(
                uri: $uri,
                raw: $raw,
                etag: sha1($raw),
                size: strlen($raw),
                uid: $this->textProperty($component, 'UID'),
                componentType: $component?->name,
                summary: $this->textProperty($component, 'SUMMARY'),
                description: $this->textProperty($component, 'DESCRIPTION'),
                location: $this->textProperty($component, 'LOCATION'),
                status: $this->textProperty($component, 'STATUS'),
                url: $this->textProperty($component, 'URL'),
                startsAt: $startsAt,
                endsAt: $endsAt,
                isAllDay: $isAllDay,
                isRecurring: $isRecurring,
                timezone: $this->timezoneFromProperty($startsAtProperty) ?? $this->timezoneFromProperty($endsAtProperty),
            );
        } finally {
            $vCalendar->destroy();
        }
    }

    private function firstCalendarComponent(VCalendar $vCalendar): ?Component
    {
        foreach ($vCalendar->getBaseComponents() as $component) {
            if (in_array($component->name, ['VEVENT', 'VTODO', 'VJOURNAL'], true)) {
                return $component;
            }
        }

        return null;
    }

    private function textProperty(?Component $component, string $name): ?string
    {
        if ($component === null || ! isset($component->{$name})) {
            return null;
        }

        return (string) $component->{$name};
    }

    private function dateTimeProperty(?Component $component, string $name): ?DateTime
    {
        if ($component === null || ! isset($component->{$name}) || ! ($component->{$name} instanceof DateTime)) {
            return null;
        }

        return $component->{$name};
    }

    private function carbonFromProperty(?DateTime $property): ?CarbonImmutable
    {
        if ($property === null) {
            return null;
        }

        return CarbonImmutable::instance($property->getDateTime(new DateTimeZone('UTC')))->utc();
    }

    private function isAllDay(?DateTime $property): bool
    {
        return $property !== null && ! $property->hasTime();
    }

    private function timezoneFromProperty(?DateTime $property): ?string
    {
        if ($property === null) {
            return null;
        }

        if (isset($property['TZID']) && $property['TZID'] instanceof Parameter) {
            return $property['TZID']->getValue();
        }

        $dateTime = $property->getDateTime(new DateTimeZone('UTC'));

        return $dateTime->getTimezone()->getName();
    }

    private function impliedEventEnd(?Component $component, ?CarbonImmutable $startsAt, ?DateTime $startsAtProperty): ?CarbonImmutable
    {
        if ($component === null || $component->name !== 'VEVENT' || $startsAt === null || $startsAtProperty === null) {
            return null;
        }

        if (isset($component->DURATION) && $component->DURATION instanceof Duration) {
            return $startsAt->add($component->DURATION->getDateInterval());
        }

        if (! $startsAtProperty->hasTime()) {
            return $startsAt->addDay();
        }

        return null;
    }
}
