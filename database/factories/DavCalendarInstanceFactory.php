<?php

namespace Bambamboole\LaravelDav\Database\Factories;

use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DavCalendarInstance>
 */
class DavCalendarInstanceFactory extends Factory
{
    protected $model = DavCalendarInstance::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'dav_calendar_id' => DavCalendar::factory(),
            'owner_id' => (Dav::ownerModel())::factory(),
            'uri' => fake()->unique()->slug(2),
            'access' => DavCalendarInstance::AccessOwner,
            'display_name' => fake()->words(2, true),
            'description' => null,
            'color' => fake()->hexColor(),
            'timezone' => null,
            'order' => 0,
            'transparent' => false,
            'share_href' => null,
            'share_display_name' => null,
            'share_invite_status' => null,
        ];
    }
}
