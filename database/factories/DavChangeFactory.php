<?php

namespace Bambamboole\LaravelDav\Database\Factories;

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DavChange>
 */
class DavChangeFactory extends Factory
{
    protected $model = DavChange::class;

    /** @return array<string, mixed> */
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
}
