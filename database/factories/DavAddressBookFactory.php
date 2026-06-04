<?php

namespace Bambamboole\LaravelDav\Database\Factories;

use Bambamboole\LaravelDav\Models\DavAddressBook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DavAddressBook>
 */
class DavAddressBookFactory extends Factory
{
    protected $model = DavAddressBook::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => config('dav.owner_model')::factory(),
            'uri' => fake()->unique()->slug(2),
            'display_name' => fake()->words(2, true),
            'description' => null,
            'sync_token' => 1,
        ];
    }
}
