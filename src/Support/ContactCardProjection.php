<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Dto\Contact\ContactEmailAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactPhoneNumber;
use Bambamboole\LaravelDav\Dto\ContactData;

class ContactCardProjection
{
    /**
     * @return array<string, mixed>
     */
    public function attributesFromData(ContactData $data): array
    {
        return [
            'uid' => $data->uid,
            'full_name' => $data->formattedName,
            'given_name' => $data->givenName,
            'family_name' => $data->familyName,
            'organization' => $data->organization,
            'contact_type' => $data->contactType,
            'name_prefix' => $data->namePrefix,
            'middle_name' => $data->middleName,
            'previous_family_name' => $data->previousFamilyName,
            'name_suffix' => $data->nameSuffix,
            'nickname' => $data->nickname,
            'phonetic_given_name' => $data->phoneticGivenName,
            'phonetic_middle_name' => $data->phoneticMiddleName,
            'phonetic_family_name' => $data->phoneticFamilyName,
            'phonetic_organization' => $data->phoneticOrganization,
            'job_title' => $data->jobTitle,
            'department' => $data->department,
            'note' => $data->note,
            'birthday' => $data->birthday,
            'pronouns' => $data->pronouns,
            'emails' => $data->emails !== [] ? array_map(fn (ContactEmailAddress $email): string => $email->value, $data->emails) : $data->simpleEmails,
            'phones' => $data->phones !== [] ? array_map(fn (ContactPhoneNumber $phone): string => $phone->value, $data->phones) : $data->simplePhones,
            'phone_numbers' => $data->phones,
            'email_addresses' => $data->emails,
            'addresses' => $data->addresses,
            'urls' => $data->urls,
            'instant_messages' => $data->instantMessages,
            'social_profiles' => $data->socialProfiles,
            'dates' => $data->dates,
            'relations' => $data->relations,
            'vcard_extensions' => $data->extensions,
        ];
    }
}
