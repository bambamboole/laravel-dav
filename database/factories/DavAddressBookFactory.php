<?php

namespace Bambamboole\LaravelDav\Database\Factories;

use Bambamboole\LaravelDav\Facades\Dav;
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
            'owner_id' => (Dav::ownerModel())::factory(),
            'uri' => fake()->unique()->slug(2),
            'display_name' => fake()->words(2, true),
            'description' => null,
            'sync_token' => 1,
        ];
    }
}
