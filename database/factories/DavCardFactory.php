<?php

namespace Bambamboole\LaravelDav\Database\Factories;

use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DavCard>
 */
class DavCardFactory extends Factory
{
    protected $model = DavCard::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $uid = (string) fake()->uuid();
        $givenName = fake()->firstName();
        $familyName = fake()->lastName();
        $email = fake()->safeEmail();
        $phone = fake()->phoneNumber();
        $city = fake()->city();

        return [
            'dav_address_book_id' => DavAddressBook::factory(),
            'uri' => $uid.'.vcf',
            'uid' => $uid,
            'full_name' => $givenName.' '.$familyName,
            'given_name' => $givenName,
            'family_name' => $familyName,
            'organization' => fake()->company(),
            'job_title' => fake()->jobTitle(),
            'note' => fake()->sentence(),
            'birthday' => [
                'year' => (int) fake()->dateTimeBetween('-80 years', '-20 years')->format('Y'),
                'month' => fake()->numberBetween(1, 12),
                'day' => fake()->numberBetween(1, 28),
            ],
            'email_addresses' => [
                [
                    'label' => 'work',
                    'value' => $email,
                    'types' => ['INTERNET', 'WORK'],
                ],
            ],
            'phone_numbers' => [
                [
                    'label' => 'mobile',
                    'value' => $phone,
                    'types' => ['CELL'],
                    'is_preferred' => true,
                ],
            ],
            'addresses' => [
                [
                    'label' => 'home',
                    'street' => fake()->streetAddress(),
                    'city' => $city,
                    'region' => fake()->randomElement([
                        'AL',
                        'AK',
                        'AZ',
                        'AR',
                        'CA',
                        'CO',
                        'CT',
                        'DE',
                        'FL',
                        'GA',
                        'HI',
                        'ID',
                        'IL',
                        'IN',
                        'IA',
                        'KS',
                        'KY',
                        'LA',
                        'ME',
                        'MD',
                        'MA',
                        'MI',
                        'MN',
                        'MS',
                        'MO',
                        'MT',
                        'NE',
                        'NV',
                        'NH',
                        'NJ',
                        'NM',
                        'NY',
                        'NC',
                        'ND',
                        'OH',
                        'OK',
                        'OR',
                        'PA',
                        'RI',
                        'SC',
                        'SD',
                        'TN',
                        'TX',
                        'UT',
                        'VT',
                        'VA',
                        'WA',
                        'WV',
                        'WI',
                        'WY',
                    ]),
                    'postal_code' => fake()->postcode(),
                    'country' => fake()->country(),
                    'types' => ['HOME'],
                ],
            ],
            'urls' => [
                [
                    'label' => 'home page',
                    'value' => fake()->url(),
                ],
            ],
            'instant_messages' => [
                [
                    'label' => 'Jabber',
                    'service' => 'xmpp',
                    'username' => strtolower($givenName).'.'.strtolower($familyName).'@chat.example.com',
                    'uri' => 'xmpp:'.strtolower($givenName).'.'.strtolower($familyName).'@chat.example.com',
                ],
            ],
            'social_profiles' => [
                [
                    'label' => 'LinkedIn',
                    'service' => 'linkedin',
                    'url' => 'https://www.linkedin.com/in/'.strtolower($givenName).'-'.strtolower($familyName),
                ],
            ],
            'dates' => [
                [
                    'label' => 'anniversary',
                    'year' => fake()->numberBetween(2000, 2024),
                    'month' => fake()->numberBetween(1, 12),
                    'day' => fake()->numberBetween(1, 28),
                ],
            ],
            'relations' => [
                [
                    'label' => 'assistant',
                    'name' => fake()->name(),
                ],
            ],
            'pronouns' => [
                [
                    'language' => 'en',
                    'value' => fake()->randomElement(['she/her', 'he/him', 'they/them']),
                ],
            ],
            'last_modified_at' => now(),
        ];
    }
}
