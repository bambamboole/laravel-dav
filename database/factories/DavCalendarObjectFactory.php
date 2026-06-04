<?php

namespace Bambamboole\LaravelDav\Database\Factories;

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<DavCalendarObject>
 */
class DavCalendarObjectFactory extends Factory
{
    protected $model = DavCalendarObject::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $uid = (string) fake()->uuid();
        $startsAt = Carbon::instance(fake()->dateTimeBetween('now', '+30 days'))->utc()->setSeconds(0);

        return [
            'dav_calendar_id' => DavCalendar::factory(),
            'uri' => "{$uid}.ics",
            'uid' => $uid,
            'component_type' => 'VEVENT',
            'summary' => fake()->sentence(3),
            'description' => null,
            'location' => null,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'is_all_day' => false,
            'timezone' => 'UTC',
            'last_modified_at' => now(),
        ];
    }
}
