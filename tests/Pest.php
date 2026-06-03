<?php

use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VCard;

uses(Bambamboole\LaravelDav\Tests\TestCase::class)->in('Feature', 'Unit', 'Smoke');

/**
 * @param  array<string, string|array{value: string, parameters?: array<string, string>}>  $properties
 */
function calendarObjectPayload(string $componentName, array $properties): string
{
    $calendar = new VCalendar([], false);
    $calendar->add('VERSION', '2.0');
    $calendar->add('PRODID', '-//Life OS//Tests//EN');
    $component = $calendar->createComponent($componentName, [], false);

    foreach ($properties as $name => $value) {
        if (is_array($value)) {
            $component->add($name, $value['value'], $value['parameters'] ?? []);

            continue;
        }

        $component->add($name, $value);
    }

    $calendar->add($component);

    return $calendar->serialize();
}

/**
 * @param  array<string, string|array{value: string|array<int, string>, parameters?: array<string, string>}|array<int, array{value: string, parameters?: array<string, string>}>>  $properties
 */
function contactCardPayload(array $properties): string
{
    $card = new VCard([], false);
    $card->add('VERSION', '3.0');
    $card->add('PRODID', '-//Life OS//Tests//EN');

    foreach ($properties as $name => $value) {
        if (is_array($value) && array_is_list($value)) {
            foreach ($value as $property) {
                $card->add($name, $property['value'], $property['parameters'] ?? []);
            }

            continue;
        }

        if (is_array($value)) {
            $card->add($name, $value['value'], $value['parameters'] ?? []);

            continue;
        }

        $card->add($name, $value);
    }

    return $card->serialize();
}
