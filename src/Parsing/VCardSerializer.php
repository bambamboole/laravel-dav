<?php

namespace Bambamboole\LaravelDav\Parsing;

use Bambamboole\LaravelDav\Dto\Contact\ContactDate;
use Bambamboole\LaravelDav\Dto\Contact\ContactEmailAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactInstantMessage;
use Bambamboole\LaravelDav\Dto\Contact\ContactPhoneNumber;
use Bambamboole\LaravelDav\Dto\Contact\ContactPostalAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactUrl;
use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Parsing\Concerns\ManipulatesVObject;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;

class VCardSerializer
{
    use ManipulatesVObject;

    public function serialize(ContactData $data): string
    {
        $vCard = new VCard([], false);
        $vCard->add('VERSION', '3.0');
        $vCard->add('PRODID', '-//LaravelDav//Contacts//EN');
        $vCard->add('UID', (string) $data->uid);

        $this->addIfPresent($vCard, 'FN', $data->formattedName);

        $vCard->add('N', [
            $data->familyName ?? '',
            $data->givenName ?? '',
            $data->middleName ?? '',
            $data->namePrefix ?? '',
            $data->nameSuffix ?? '',
        ]);

        if ($data->contactType === 'organization') {
            $vCard->add('X-ABShowAs', 'COMPANY');
        }

        $this->addIfPresent($vCard, 'NICKNAME', $data->nickname);
        $this->addIfPresent($vCard, 'X-PHONETIC-FIRST-NAME', $data->phoneticGivenName);
        $this->addIfPresent($vCard, 'X-PHONETIC-MIDDLE-NAME', $data->phoneticMiddleName);
        $this->addIfPresent($vCard, 'X-PHONETIC-LAST-NAME', $data->phoneticFamilyName);
        $this->addIfPresent($vCard, 'X-PHONETIC-ORG', $data->phoneticOrganization);
        $this->addIfPresent($vCard, 'X-MAIDEN-NAME', $data->previousFamilyName);

        if (! empty($data->organization)) {
            $vCard->add('ORG', array_filter([
                $data->organization,
                $data->department,
            ], fn (?string $value): bool => filled($value)));
        }

        $this->addIfPresent($vCard, 'TITLE', $data->jobTitle);

        if ($data->birthday instanceof ContactDate) {
            $vCard->add('BDAY', $this->dateValue($data->birthday));
        }

        $this->addIfPresent($vCard, 'NOTE', $data->note);

        foreach ($data->pronouns as $pronoun) {
            if ($pronoun->value === '') {
                continue;
            }

            $vCard->add('PRONOUNS', $pronoun->value, array_filter([
                'LANGUAGE' => $pronoun->language,
            ]));
        }

        $group = 1;

        foreach ($data->emailAddresses as $email) {
            $this->addGroupedProperty($vCard, 'EMAIL', $email->value, $email->label, $this->parameters($email), $group);
        }

        foreach ($data->phoneNumbers as $phone) {
            $this->addGroupedProperty($vCard, 'TEL', $phone->value, $phone->label, $this->parameters($phone), $group);
        }

        foreach ($data->addresses as $address) {
            $this->addGroupedProperty($vCard, 'ADR', [
                $address->poBox ?? '',
                $address->extended ?? '',
                $address->street ?? '',
                $address->city ?? '',
                $address->region ?? '',
                $address->postalCode ?? '',
                $address->country ?? '',
            ], $address->label, $this->parameters($address), $group);
        }

        foreach ($data->urls as $url) {
            $this->addGroupedProperty($vCard, 'URL', $url->value, $url->label, $this->parameters($url), $group);
        }

        foreach ($data->instantMessages as $instantMessage) {
            $this->addGroupedProperty(
                $vCard,
                'IMPP',
                $instantMessage->uri ?? ($instantMessage->service.':'.$instantMessage->username),
                $instantMessage->label,
                $this->parameters($instantMessage),
                $group,
            );
        }

        foreach ($data->socialProfiles as $socialProfile) {
            $this->addGroupedProperty(
                $vCard,
                'X-SOCIALPROFILE',
                $socialProfile->url ?? $socialProfile->username,
                $socialProfile->label,
                array_filter(['TYPE' => $socialProfile->service]),
                $group,
            );
        }

        foreach ($data->relations as $relation) {
            $this->addGroupedProperty($vCard, 'X-ABRELATEDNAMES', $relation->name, $relation->label, [], $group);
        }

        foreach ($data->dates as $date) {
            $this->addGroupedProperty($vCard, 'X-ABDATE', $this->dateValue($date), $date->label, [], $group);
        }

        foreach ($data->extensions as $extension) {
            if ($extension->name === '') {
                continue;
            }

            $property = $vCard->add($extension->name, $extension->value, $extension->parameters);

            if ($property instanceof Property && $extension->group !== null) {
                $property->group = $extension->group;
            }
        }

        return $vCard->serialize();
    }

    public function merge(string $existingPayload, ContactData $data): string
    {
        $vCard = Reader::read($existingPayload);

        try {
            if (! $vCard instanceof VCard) {
                return $this->serialize($data);
            }

            $this->setOrRemove($vCard, 'FN', $data->formattedName);

            unset($vCard->N);
            $vCard->add('N', [
                $data->familyName ?? '',
                $data->givenName ?? '',
                $data->middleName ?? '',
                $data->namePrefix ?? '',
                $data->nameSuffix ?? '',
            ]);

            $this->setOrRemove($vCard, 'NICKNAME', $data->nickname);
            $this->setOrRemove($vCard, 'TITLE', $data->jobTitle);
            $this->setOrRemove($vCard, 'NOTE', $data->note);
            $this->setOrRemove($vCard, 'X-PHONETIC-FIRST-NAME', $data->phoneticGivenName);
            $this->setOrRemove($vCard, 'X-PHONETIC-MIDDLE-NAME', $data->phoneticMiddleName);
            $this->setOrRemove($vCard, 'X-PHONETIC-LAST-NAME', $data->phoneticFamilyName);
            $this->setOrRemove($vCard, 'X-PHONETIC-ORG', $data->phoneticOrganization);
            $this->setOrRemove($vCard, 'X-MAIDEN-NAME', $data->previousFamilyName);

            unset($vCard->ORG);
            if (! empty($data->organization)) {
                $vCard->add('ORG', array_values(array_filter(
                    [$data->organization, $data->department],
                    fn (?string $v): bool => filled($v),
                )));
            }

            $group = $this->nextItemNumber($vCard);
            $this->setPrimaryTextProperty($vCard, 'EMAIL', $this->primaryEmail($data), ['TYPE' => ['INTERNET']], $group);
            $this->setPrimaryTextProperty($vCard, 'TEL', $this->primaryPhone($data), ['TYPE' => ['CELL']], $group);

            return $vCard->serialize();
        } finally {
            $vCard->destroy();
        }
    }

    /**
     * @param  array<int, string>|string  $value
     * @param  array<string, mixed>  $parameters
     */
    private function addGroupedProperty(VCard $vCard, string $name, array|string|null $value, ?string $label, array $parameters, int &$group): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $property = $vCard->add($name, $value, $parameters);

        if ($label === null || $label === '') {
            return;
        }

        $groupName = 'item'.$group++;
        if (! $property instanceof Property) {
            return;
        }

        $property->group = $groupName;
        $labelProperty = $vCard->add('X-ABLABEL', $this->appleLabel($label));

        if ($labelProperty instanceof Property) {
            $labelProperty->group = $groupName;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parameters(ContactEmailAddress|ContactInstantMessage|ContactPhoneNumber|ContactPostalAddress|ContactUrl $value): array
    {
        $types = collect($value->types ?? [])
            ->when($value->isPreferred ?? false, fn ($types) => $types->push('pref'))
            ->filter()
            ->values()
            ->all();

        return $types === [] ? [] : ['TYPE' => $types];
    }

    private function dateValue(ContactDate $date): string
    {
        if ($date->year !== null) {
            return sprintf('%04d-%02d-%02d', $date->year, $date->month, $date->day);
        }

        return sprintf('--%02d-%02d', $date->month, $date->day);
    }

    private function appleLabel(string $label): string
    {
        return match (strtolower($label)) {
            'home page', 'homepage' => '_$!<HomePage>!$_',
            'home' => '_$!<Home>!$_',
            'work' => '_$!<Work>!$_',
            default => $label,
        };
    }

    private function primaryEmail(ContactData $data): ?ContactEmailAddress
    {
        return $data->emailAddresses[0] ?? null;
    }

    private function primaryPhone(ContactData $data): ?ContactPhoneNumber
    {
        return $data->phoneNumbers[0] ?? null;
    }

    /**
     * Updates only the first modeled property and preserves additional
     * properties of the same name. This keeps client-owned secondary values and
     * unknown grouped metadata intact while still applying app-owned primary
     * field edits.
     *
     * @param  array<string, mixed>  $defaultParameters
     */
    private function setPrimaryTextProperty(
        VCard $vCard,
        string $name,
        ContactEmailAddress|ContactPhoneNumber|null $value,
        array $defaultParameters,
        int &$group,
    ): void {
        if ($value === null || $value->value === '') {
            return;
        }

        $existing = $vCard->select($name);
        $first = $existing[array_key_first($existing)] ?? null;

        if ($first instanceof Property) {
            $first->setValue($value->value);

            return;
        }

        $parameters = $this->parameters($value);
        $this->addGroupedProperty(
            $vCard,
            $name,
            $value->value,
            $value->label,
            $parameters === [] ? $defaultParameters : $parameters,
            $group,
        );
    }

    private function nextItemNumber(VCard $vCard): int
    {
        $max = 0;

        foreach ($vCard->children() as $child) {
            if (! $child instanceof Property || $child->group === null) {
                continue;
            }

            if (preg_match('/^item(?<number>\d+)$/i', $child->group, $matches) === 1) {
                $max = max($max, (int) $matches['number']);
            }
        }

        return $max + 1;
    }
}
