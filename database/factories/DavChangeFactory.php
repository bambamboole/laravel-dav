<?php

namespace Bambamboole\LaravelDav\Database\Factories;

use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DavChange>
 */
class DavChangeFactory extends Factory
{
    protected $model = DavChange::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collection_type' => 'calendar',
            'collection_id' => DavCalendar::factory(),
            'resource_uri' => fake()->uuid().'.ics',
            'operation' => 1,
            'sync_token' => 1,
            'created_at' => now(),
        ];
    }

    /**
     * Create a change for an address book collection.
     */
    public function addressBook(): static
    {
        return $this->state(fn (array $attributes) => [
            'collection_type' => 'address_book',
            'collection_id' => DavAddressBook::factory(),
            'resource_uri' => fake()->uuid().'.vcf',
        ]);
    }

    /**
     * Mark the changed resource as deleted.
     */
    public function deletedResource(): static
    {
        return $this->state(function (array $attributes) {
            $extension = ($attributes['collection_type'] ?? 'calendar') === 'address_book' ? 'vcf' : 'ics';

            return [
                'resource_uri' => $attributes['resource_uri'] ?? fake()->uuid().'.'.$extension,
                'operation' => 3,
            ];
        });
    }
}
