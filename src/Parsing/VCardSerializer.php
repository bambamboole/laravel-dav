<?php

namespace Bambamboole\LaravelDav\Parsing;

use Bambamboole\LaravelDav\Dto\Contact\ContactDate;
use Bambamboole\LaravelDav\Dto\Contact\ContactEmailAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactInstantMessage;
use Bambamboole\LaravelDav\Dto\Contact\ContactPhoneNumber;
use Bambamboole\LaravelDav\Dto\Contact\ContactPostalAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactUrl;
use Bambamboole\LaravelDav\Dto\ContactData;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Document;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;

class VCardSerializer
{
    public function serialize(ContactData $data): string
    {
        $vCard = new VCard([], false);
        $vCard->add('VERSION', '3.0');
        $vCard->add('PRODID', '-//Life OS//Contacts//EN');
        $vCard->add('UID', (string) $data->uid);

        if (! empty($data->formattedName)) {
            $vCard->add('FN', $data->formattedName);
        }

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

        if (! empty($data->nickname)) {
            $vCard->add('NICKNAME', $data->nickname);
        }

        if (! empty($data->organization)) {
            $vCard->add('ORG', array_filter([
                $data->organization,
                $data->department,
            ], fn (?string $value): bool => filled($value)));
        }

        if (! empty($data->jobTitle)) {
            $vCard->add('TITLE', $data->jobTitle);
        }

        if ($data->birthday instanceof ContactDate) {
            $vCard->add('BDAY', $this->dateValue($data->birthday));
        }

        if (! empty($data->note)) {
            $vCard->add('NOTE', $data->note);
        }

        foreach ($data->pronouns as $pronoun) {
            if ($pronoun->value === '') {
                continue;
            }

            $vCard->add('PRONOUNS', $pronoun->value, array_filter([
                'LANGUAGE' => $pronoun->language,
            ]));
        }

        $group = 1;

        if ($data->emails !== []) {
            foreach ($data->emails as $email) {
                $this->addGroupedProperty($vCard, 'EMAIL', $email->value, $email->label, $this->parameters($email), $group);
            }
        } else {
            foreach ($data->simpleEmails as $email) {
                $vCard->add('EMAIL', $email, ['TYPE' => 'INTERNET']);
            }
        }

        if ($data->phones !== []) {
            foreach ($data->phones as $phone) {
                $this->addGroupedProperty($vCard, 'TEL', $phone->value, $phone->label, $this->parameters($phone), $group);
            }
        } else {
            foreach ($data->simplePhones as $phone) {
                $vCard->add('TEL', $phone, ['TYPE' => 'CELL']);
            }
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

            unset($vCard->ORG);
            if (! empty($data->organization)) {
                $vCard->add('ORG', array_values(array_filter(
                    [$data->organization, $data->department],
                    fn (?string $v): bool => filled($v),
                )));
            }

            return $vCard->serialize();
        } finally {
            $vCard->destroy();
        }
    }

    private function setOrRemove(Document $vCard, string $name, ?string $value): void
    {
        unset($vCard->{$name});
        if (! empty($value)) {
            $vCard->add($name, $value);
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
            default => $label,
        };
    }
}
