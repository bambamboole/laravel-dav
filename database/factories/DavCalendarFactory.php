<?php

namespace Bambamboole\LaravelDav\Database\Factories;

use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DavCalendar>
 */
class DavCalendarFactory extends Factory
{
    protected $model = DavCalendar::class;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withInstance(array $attributes = []): static
    {
        return $this->has(
            DavCalendarInstance::factory()
                ->state(fn (array $instanceAttributes, DavCalendar $calendar): array => [
                    'owner_id' => $calendar->owner_id,
                ])
                ->state($attributes),
            'instances',
        );
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'owner_id' => (Dav::ownerModel())::factory(),
            'components' => ['VEVENT', 'VTODO', 'VJOURNAL'],
            'sync_token' => 1,
        ];
    }
}
