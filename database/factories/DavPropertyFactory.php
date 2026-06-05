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

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'path' => '/dav/'.fake()->uuid(),
            'name' => '{DAV:}displayname',
            'value_type' => 'string',
            'value' => fake()->words(2, true),
        ];
    }
}
