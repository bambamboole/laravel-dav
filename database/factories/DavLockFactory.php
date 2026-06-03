<?php

namespace Bambamboole\LaravelDav\Database\Factories;

use Bambamboole\LaravelDav\Models\DavLock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DavLock>
 */
class DavLockFactory extends Factory
{
    protected $model = DavLock::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner' => fake()->name(),
            'timeout' => 3600,
            'created' => now()->timestamp,
            'token' => 'opaquelocktoken:'.fake()->uuid(),
            'scope' => 1,
            'depth' => 0,
            'uri' => '/dav/'.fake()->uuid(),
        ];
    }
}
