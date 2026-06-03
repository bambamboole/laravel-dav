<?php

namespace Bambamboole\LaravelDav\Database\Factories;

use Bambamboole\LaravelDav\Models\DavCalendar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DavCalendar>
 */
class DavCalendarFactory extends Factory
{
    protected $model = DavCalendar::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => config('dav.owner_model')::factory(),
            'uri' => fake()->unique()->slug(2),
            'display_name' => fake()->words(2, true),
            'description' => null,
            'color' => fake()->hexColor(),
            'timezone' => 'UTC',
            'components' => ['VEVENT', 'VTODO'],
            'sync_token' => 1,
        ];
    }
}
