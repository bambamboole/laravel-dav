<?php

namespace Bambamboole\LaravelDav\Tests\Stubs;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<OwnerUser>
 */
class OwnerUserFactory extends Factory
{
    protected $model = OwnerUser::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
        ];
    }
}
