<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Dto\CalendarObjectData;

class CalendarObjectProjection
{
    /**
     * @return array<string, mixed>
     */
    public function attributesFromData(CalendarObjectData $data, ?string $defaultComponentType = 'VEVENT'): array
    {
        return [
            'uid' => $data->uid,
            'component_type' => $data->componentType ?? $defaultComponentType,
            'summary' => $data->summary,
            'description' => $data->description,
            'location' => $data->location,
            'status' => $data->status,
            'url' => $data->url,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'is_all_day' => $data->isAllDay,
            'timezone' => $data->timezone,
        ];
    }
}
