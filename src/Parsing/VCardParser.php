<?php

namespace Bambamboole\LaravelDav\Parsing;

use Bambamboole\LaravelDav\Dto\Contact\ContactDate;
use Bambamboole\LaravelDav\Dto\Contact\ContactEmailAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactInstantMessage;
use Bambamboole\LaravelDav\Dto\Contact\ContactPhoneNumber;
use Bambamboole\LaravelDav\Dto\Contact\ContactPostalAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactPronoun;
use Bambamboole\LaravelDav\Dto\Contact\ContactRelation;
use Bambamboole\LaravelDav\Dto\Contact\ContactSocialProfile;
use Bambamboole\LaravelDav\Dto\Contact\ContactUrl;
use Bambamboole\LaravelDav\Dto\Contact\ContactVCardExtension;
use Bambamboole\LaravelDav\Dto\ContactData;
use Sabre\VObject\Component;
use Sabre\VObject\Property;
use Sabre\VObject\Property\VCard\DateAndOrTime;
use Sabre\VObject\Reader;

class VCardParser
{
    public function parse(string $raw, string $uri = ''): ContactData
    {
        $vCard = Reader::read($raw);

        try {
            $nameParts = $this->parts($vCard, 'N');
            $organizationParts = $this->parts($vCard, 'ORG');

            $birthday = $this->dateProperty($vCard, 'BDAY');

            return new ContactData(
                uri: $uri,
                raw: $raw,
                etag: sha1($raw),
                size: strlen($raw),
                uid: $this->textProperty($vCard, 'UID'),
                formattedName: $this->textProperty($vCard, 'FN'),
                givenName: $nameParts[1] ?? null,
                familyName: $nameParts[0] ?? null,
                organization: $organizationParts[0] ?? $this->textProperty($vCard, 'ORG'),
                contactType: $this->contactType($vCard),
                birthday: $birthday !== null ? new ContactDate($birthday) : null,
                emails: array_map(fn (array $row): ContactEmailAddress => new ContactEmailAddress($row), $this->labeledTextProperties($vCard, 'EMAIL')),
                phones: array_map(fn (array $row): ContactPhoneNumber => new ContactPhoneNumber($row), $this->labeledTextProperties($vCard, 'TEL')),
                addresses: array_map(fn (array $row): ContactPostalAddress => new ContactPostalAddress($row), $this->addresses($vCard)),
                urls: array_map(fn (array $row): ContactUrl => new ContactUrl($row), $this->labeledTextProperties($vCard, 'URL')),
                instantMessages: array_map(fn (array $row): ContactInstantMessage => new ContactInstantMessage($row), $this->instantMessages($vCard)),
                socialProfiles: array_map(fn (array $row): ContactSocialProfile => new ContactSocialProfile($row), $this->socialProfiles($vCard)),
                dates: array_map(fn (array $row): ContactDate => new ContactDate($row), $this->dates($vCard)),
                relations: array_map(fn (array $row): ContactRelation => new ContactRelation($row), $this->relations($vCard)),
                extensions: array_map(fn (array $row): ContactVCardExtension => new ContactVCardExtension($row), $this->extensions($vCard)),
                pronouns: array_map(fn (array $row): ContactPronoun => new ContactPronoun($row), $this->pronouns($vCard)),
                middleName: $nameParts[2] ?? null,
                namePrefix: $nameParts[3] ?? null,
                nameSuffix: $nameParts[4] ?? null,
                nickname: $this->textProperty($vCard, 'NICKNAME'),
                jobTitle: $this->textProperty($vCard, 'TITLE'),
                department: $organizationParts[1] ?? null,
                note: $this->textProperty($vCard, 'NOTE'),
                simpleEmails: $this->propertyValues($vCard, 'EMAIL'),
                simplePhones: $this->propertyValues($vCard, 'TEL'),
            );
        } finally {
            $vCard->destroy();
        }
    }

    private function contactType(Component $component): string
    {
        $showAs = $this->textProperty($component, 'X-ABShowAs');
        $kind = $this->textProperty($component, 'KIND');

        if (strtolower((string) $showAs) === 'company' || in_array(strtolower((string) $kind), ['org', 'organization'], true)) {
            return 'organization';
        }

        return 'person';
    }

    private function textProperty(Component $component, string $name): ?string
    {
        if (! isset($component->{$name})) {
            return null;
        }

        return (string) $component->{$name};
    }

    /**
     * @return array<int, string>
     */
    private function propertyValues(Component $component, string $name): array
    {
        return collect($component->select($name))
            ->map(fn (Property $property): string => (string) $property)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: ?string, value: string, types: array<int, string>, is_preferred: bool, group: ?string}>
     */
    private function labeledTextProperties(Component $component, string $name): array
    {
        return collect($component->select($name))
            ->map(fn (Property $property): array => [
                'label' => $this->labelFor($component, $property),
                'value' => (string) $property,
                'types' => $this->parameterValues($property, 'TYPE'),
                'is_preferred' => $this->isPreferred($property),
                'group' => $this->group($property),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function addresses(Component $component): array
    {
        return collect($component->select('ADR'))
            ->map(function (Property $property) use ($component): array {
                $parts = $property->getParts();

                return [
                    'label' => $this->labelFor($component, $property),
                    'po_box' => $parts[0] ?? null,
                    'extended' => $parts[1] ?? null,
                    'street' => $parts[2] ?? null,
                    'city' => $parts[3] ?? null,
                    'region' => $parts[4] ?? null,
                    'postal_code' => $parts[5] ?? null,
                    'country' => $parts[6] ?? null,
                    'country_code' => null,
                    'types' => $this->parameterValues($property, 'TYPE'),
                    'is_preferred' => $this->isPreferred($property),
                    'group' => $this->group($property),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function instantMessages(Component $component): array
    {
        $messages = collect($component->select('IMPP'))
            ->map(function (Property $property) use ($component): array {
                $uri = (string) $property;
                [$service, $username] = str_contains($uri, ':')
                    ? explode(':', $uri, 2)
                    : [null, $uri];

                return [
                    'label' => $this->labelFor($component, $property),
                    'service' => $service,
                    'username' => $username,
                    'uri' => $uri,
                    'types' => $this->parameterValues($property, 'TYPE'),
                    'is_preferred' => $this->isPreferred($property),
                    'group' => $this->group($property),
                ];
            });

        foreach (['X-JABBER' => 'jabber', 'X-AIM' => 'aim', 'X-SKYPE' => 'skype'] as $propertyName => $service) {
            foreach ($component->select($propertyName) as $property) {
                $messages->push([
                    'label' => $this->labelFor($component, $property),
                    'service' => $service,
                    'username' => (string) $property,
                    'uri' => $service.':'.(string) $property,
                    'types' => $this->parameterValues($property, 'TYPE'),
                    'is_preferred' => $this->isPreferred($property),
                    'group' => $this->group($property),
                ]);
            }
        }

        return $messages->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function socialProfiles(Component $component): array
    {
        return collect($component->select('X-SOCIALPROFILE'))
            ->map(fn (Property $property): array => [
                'label' => $this->labelFor($component, $property),
                'service' => $this->parameterValues($property, 'TYPE')[0] ?? null,
                'username' => null,
                'url' => (string) $property,
                'user_identifier' => null,
                'group' => $this->group($property),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{language: ?string, value: string, group: ?string}>
     */
    private function pronouns(Component $component): array
    {
        return collect($component->select('PRONOUNS'))
            ->map(fn (Property $property): array => [
                'language' => $this->parameterValues($property, 'LANGUAGE')[0] ?? null,
                'value' => (string) $property,
                'group' => $this->group($property),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dates(Component $component): array
    {
        return collect($component->select('X-ABDATE'))
            ->map(fn (Property $property): ?array => $this->dateFromProperty($component, $property))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: ?string, name: string, group: ?string}>
     */
    private function relations(Component $component): array
    {
        return collect($component->select('X-ABRELATEDNAMES'))
            ->map(fn (Property $property): array => [
                'label' => $this->labelFor($component, $property),
                'name' => (string) $property,
                'group' => $this->group($property),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extensions(Component $component): array
    {
        $parsed = [
            'X-ABDATE',
            'X-ABLABEL',
            'X-ABRELATEDNAMES',
            'X-ABSHOWAS',
            'X-AIM',
            'X-JABBER',
            'X-SKYPE',
            'X-SOCIALPROFILE',
        ];

        return collect($component->children())
            ->filter(fn (mixed $child): bool => $child instanceof Property)
            ->filter(fn (Property $property): bool => str_starts_with($property->name, 'X-'))
            ->filter(fn (Property $property): bool => ! in_array($property->name, $parsed, true))
            ->map(fn (Property $property): array => [
                'name' => $property->name,
                'value' => (string) $property,
                'group' => $this->group($property),
                'parameters' => $this->parameters($property),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dateProperty(Component $component, string $name): ?array
    {
        if (! isset($component->{$name}) || ! $component->{$name} instanceof Property) {
            return null;
        }

        return $this->dateFromProperty($component, $component->{$name});
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dateFromProperty(Component $component, Property $property): ?array
    {
        $rawValue = (string) $property;
        $year = null;
        $month = 0;
        $day = 0;

        if ($property instanceof DateAndOrTime) {
            $dateTime = $property->getDateTime();
            $year = (int) $dateTime->format('Y');
            $month = (int) $dateTime->format('m');
            $day = (int) $dateTime->format('d');
        } elseif (preg_match('/^(?<year>\d{4})-?(?<month>\d{2})-?(?<day>\d{2})$/', $rawValue, $matches)) {
            $year = (int) $matches['year'];
            $month = (int) $matches['month'];
            $day = (int) $matches['day'];
        } elseif (preg_match('/^--(?<month>\d{2})-?(?<day>\d{2})$/', $rawValue, $matches)) {
            $month = (int) $matches['month'];
            $day = (int) $matches['day'];
        }

        if ($month < 1 || $day < 1) {
            return null;
        }

        return [
            'label' => $this->labelFor($component, $property),
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'calendar' => null,
            'raw_value' => $rawValue,
            'group' => $this->group($property),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function parts(Component $component, string $name): array
    {
        if (! isset($component->{$name}) || ! $component->{$name} instanceof Property) {
            return [];
        }

        return $component->{$name}->getParts();
    }

    private function labelFor(Component $component, Property $property): ?string
    {
        $group = $this->group($property);

        if ($group !== null) {
            foreach ($component->select('X-ABLABEL') as $labelProperty) {
                if ($labelProperty instanceof Property && $this->group($labelProperty) === $group) {
                    return $this->normalizeAppleLabel((string) $labelProperty);
                }
            }
        }

        $types = $this->parameterValues($property, 'TYPE');

        return $types[0] ?? null;
    }

    private function normalizeAppleLabel(string $label): string
    {
        if (preg_match('/^_\$!<(?<label>.+)>!\$_$/', $label, $matches)) {
            return strtolower(trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $matches['label']) ?? $matches['label']));
        }

        return $label;
    }

    /**
     * @return array<int, string>
     */
    private function parameterValues(Property $property, string $name): array
    {
        $parameters = $property->parameters();
        $normalizedName = strtoupper($name);

        if (! isset($parameters[$normalizedName])) {
            return [];
        }

        $values = [];

        foreach ($parameters[$normalizedName] as $value) {
            $values[] = (string) $value;
        }

        return $values;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    private function parameters(Property $property): array
    {
        $parameters = [];

        foreach ($property->parameters() as $name => $parameter) {
            $values = [];

            foreach ($parameter as $value) {
                $values[] = (string) $value;
            }

            $parameters[$name] = count($values) > 1
                ? $values
                : ($values[0] ?? (string) $parameter);
        }

        return $parameters;
    }

    private function isPreferred(Property $property): bool
    {
        return collect($this->parameterValues($property, 'TYPE'))
            ->contains(fn (string $type): bool => strtolower($type) === 'pref');
    }

    private function group(Property $property): ?string
    {
        $group = $property->group;

        return is_string($group) && $group !== '' ? $group : null;
    }
}
