<?php

namespace Bambamboole\LaravelDav\Database\Factories;

use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavCalendarSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DavCalendarSubscription>
 */
class DavCalendarSubscriptionFactory extends Factory
{
    protected $model = DavCalendarSubscription::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'owner_id' => (Dav::ownerModel())::factory(),
            'uri' => fake()->unique()->slug(2),
            'source' => fake()->url(),
            'display_name' => fake()->words(2, true),
            'description' => null,
            'color' => fake()->hexColor(),
            'refresh_rate' => null,
            'order' => 0,
            'strip_todos' => false,
            'strip_alarms' => false,
            'strip_attachments' => false,
            'last_modified_at' => null,
        ];
    }
}
