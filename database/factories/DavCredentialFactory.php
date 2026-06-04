<?php

namespace Bambamboole\LaravelDav\Database\Factories;

use Bambamboole\LaravelDav\Models\DavCredential;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<DavCredential>
 */
class DavCredentialFactory extends Factory
{
    protected $model = DavCredential::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => config('dav.owner_model')::factory(),
            'name' => fake()->words(2, true),
            'username' => fake()->unique()->safeEmail(),
            'secret_hash' => Hash::make(Str::random(32)),
            'last_used_at' => null,
        ];
    }
}
