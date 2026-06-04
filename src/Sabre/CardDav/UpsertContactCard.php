<?php

namespace Bambamboole\LaravelDav\Sabre\CardDav;

use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCard;
use Bambamboole\LaravelDav\Parsing\VCardParser;

class UpsertContactCard
{
    public function __construct(private VCardParser $parser) {}

    public function handle(DavAddressBook $addressBook, string $uri, string $payload): DavCard
    {
        $parsed = $this->parser->parse($payload, $uri);

        return $addressBook->cards()->updateOrCreate(
            ['uri' => $uri],
            [
                'uid' => $parsed->uid,
                'full_name' => $parsed->formattedName,
                'given_name' => $parsed->givenName,
                'family_name' => $parsed->familyName,
                'middle_name' => $parsed->middleName,
                'name_prefix' => $parsed->namePrefix,
                'name_suffix' => $parsed->nameSuffix,
                'phonetic_given_name' => $parsed->phoneticGivenName,
                'phonetic_middle_name' => $parsed->phoneticMiddleName,
                'phonetic_family_name' => $parsed->phoneticFamilyName,
                'phonetic_organization' => $parsed->phoneticOrganization,
                'previous_family_name' => $parsed->previousFamilyName,
                'nickname' => $parsed->nickname,
                'organization' => $parsed->organization,
                'department' => $parsed->department,
                'job_title' => $parsed->jobTitle,
                'note' => $parsed->note,
                'contact_type' => $parsed->contactType,
                'birthday' => $parsed->birthday,
                'pronouns' => $parsed->pronouns,
                'emails' => $parsed->simpleEmails,
                'phones' => $parsed->simplePhones,
                'phone_numbers' => $parsed->phones,
                'email_addresses' => $parsed->emails,
                'addresses' => $parsed->addresses,
                'urls' => $parsed->urls,
                'instant_messages' => $parsed->instantMessages,
                'social_profiles' => $parsed->socialProfiles,
                'dates' => $parsed->dates,
                'relations' => $parsed->relations,
                'vcard_extensions' => $parsed->extensions,
                'card_data' => $payload,
                'last_modified_at' => now(),
            ],
        );
    }
}
