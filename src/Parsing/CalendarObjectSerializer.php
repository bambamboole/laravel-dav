<?php

namespace Bambamboole\LaravelDav\Parsing;

use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Parsing\Concerns\ManipulatesVObject;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Sabre\VObject\Component;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;

class CalendarObjectSerializer
{
    use ManipulatesVObject;

    public function serialize(CalendarObjectData $data): string
    {
        $calendar = new VCalendar([], false);
        $calendar->add('VERSION', '2.0');
        $calendar->add('PRODID', '-//Life OS//Calendar//EN');

        $componentType = $data->componentType ?: 'VEVENT';
        $component = $calendar->createComponent($componentType, [], false);

        $component->add('UID', (string) $data->uid);

        foreach (['summary' => 'SUMMARY', 'description' => 'DESCRIPTION', 'location' => 'LOCATION', 'status' => 'STATUS', 'url' => 'URL'] as $attribute => $property) {
            if (! empty($data->{$attribute})) {
                $component->add($property, $data->{$attribute});
            }
        }

        $isAllDay = $data->isAllDay;

        if ($data->startsAt instanceof DateTimeInterface) {
            $this->addDateTime($component, 'DTSTART', $data->startsAt, $isAllDay);
        }

        if ($data->endsAt instanceof DateTimeInterface) {
            $this->addDateTime($component, $componentType === 'VTODO' ? 'DUE' : 'DTEND', $data->endsAt, $isAllDay);
        }

        $calendar->add($component);

        return $calendar->serialize();
    }

    public function merge(string $existingPayload, CalendarObjectData $data): string
    {
        $calendar = Reader::read($existingPayload);

        try {
            $component = $calendar instanceof VCalendar ? $this->findComponent($calendar) : null;

            if ($component === null) {
                return $this->serialize($data);
            }

            $this->setOrRemove($component, 'SUMMARY', $data->summary);
            $this->setOrRemove($component, 'DESCRIPTION', $data->description);
            $this->setOrRemove($component, 'LOCATION', $data->location);
            $this->setOrRemove($component, 'STATUS', $data->status);
            $this->setOrRemove($component, 'URL', $data->url);

            $isAllDay = $data->isAllDay;
            $endProperty = $component->name === 'VTODO' ? 'DUE' : 'DTEND';

            unset($component->DTSTART);
            if ($data->startsAt instanceof DateTimeInterface) {
                $this->addDateTime($component, 'DTSTART', $data->startsAt, $isAllDay);
            }

            unset($component->{$endProperty});
            if ($data->endsAt instanceof DateTimeInterface) {
                $this->addDateTime($component, $endProperty, $data->endsAt, $isAllDay);
            }

            $this->setOrRemove($component, 'LAST-MODIFIED', gmdate('Ymd\THis\Z'));

            return $calendar->serialize();
        } finally {
            $calendar->destroy();
        }
    }

    private function findComponent(VCalendar $calendar): ?Component
    {
        $component = $calendar->getBaseComponent();

        if ($component instanceof Component && in_array($component->name, ['VEVENT', 'VTODO'], true)) {
            return $component;
        }

        return null;
    }

    private function addDateTime(Component $component, string $property, DateTimeInterface $value, bool $isAllDay): void
    {
        if ($isAllDay) {
            $component->add($property, Carbon::instance($value)->format('Ymd'), ['VALUE' => 'DATE']);

            return;
        }

        $component->add($property, Carbon::instance($value)->utc()->format('Ymd\THis\Z'));
    }
}
