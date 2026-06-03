<?php

namespace Bambamboole\LaravelDav\Database\Factories;

use Bambamboole\LaravelDav\Models\DavProperty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DavProperty>
 */
class DavPropertyFactory extends Factory
{
    protected $model = DavProperty::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => '/dav/'.fake()->uuid(),
            'name' => '{DAV:}displayname',
            'value' => fake()->words(2, true),
        ];
    }
}
