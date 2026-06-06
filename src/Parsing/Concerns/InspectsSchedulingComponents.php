<?php

namespace Bambamboole\LaravelDav\Parsing\Concerns;

use Sabre\VObject\Component;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Property;

trait InspectsSchedulingComponents
{
    /**
     * @return list<Component>
     */
    private function schedulingComponents(VCalendar $calendar): array
    {
        return array_values(array_filter(
            $calendar->getBaseComponents(),
            static fn (Component $component): bool => in_array($component->name, ['VEVENT', 'VTODO'], true),
        ));
    }

    private function componentHasAddress(Component $component, string $propertyName, string $email): bool
    {
        foreach ($component->select($propertyName) as $property) {
            if ($property instanceof Property && strtolower($property->getValue()) === 'mailto:'.strtolower($email)) {
                return true;
            }
        }

        return false;
    }
}
