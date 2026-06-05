<?php

namespace Bambamboole\LaravelDav\Database\Factories;

use Bambamboole\LaravelDav\Database\Factories\Concerns\WithoutRecordingDavChanges;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<DavCalendarObject>
 */
class DavCalendarObjectFactory extends Factory
{
    use WithoutRecordingDavChanges;

    protected $model = DavCalendarObject::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $uid = (string) fake()->uuid();
        $startsAt = Carbon::instance(fake()->dateTimeBetween('now', '+30 days'))->utc()->setSeconds(0);

        return [
            'dav_calendar_id' => DavCalendar::factory(),
            'uri' => "{$uid}.ics",
            'data' => [
                'uid' => $uid,
                'componentType' => 'VEVENT',
                'summary' => fake()->sentence(3),
                'description' => null,
                'location' => null,
                'startsAt' => $startsAt,
                'endsAt' => $startsAt->copy()->addHour(),
                'isAllDay' => false,
                'timezone' => 'UTC',
            ],
            'last_modified_at' => now(),
        ];
    }
}
