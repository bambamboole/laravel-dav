<?php

namespace Bambamboole\LaravelDav\Database\Factories;

use Bambamboole\LaravelDav\Models\DavCalendarAttachment;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DavCalendarAttachment>
 */
class DavCalendarAttachmentFactory extends Factory
{
    protected $model = DavCalendarAttachment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $managedId = (string) fake()->uuid();

        return [
            'dav_calendar_object_id' => DavCalendarObject::factory(),
            'managed_id' => $managedId,
            'filename' => $managedId.'.bin',
            'content_type' => 'application/octet-stream',
            'size' => fake()->numberBetween(1, 10000),
            'etag' => sha1($managedId),
        ];
    }
}
