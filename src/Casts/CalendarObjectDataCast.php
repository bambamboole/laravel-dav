<?php

namespace Bambamboole\LaravelDav\Casts;

use Bambamboole\LaravelDav\Casts\Concerns\DecodesJsonColumn;
use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Support\DtoFactory;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonException;

/**
 * @implements CastsAttributes<CalendarObjectData, CalendarObjectData|array<string, mixed>|null>
 */
class CalendarObjectDataCast implements CastsAttributes
{
    use DecodesJsonColumn;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): CalendarObjectData
    {
        return DtoFactory::calendarObjectData($this->decode($value), [
            'uri' => (string) ($attributes['uri'] ?? ''),
            'raw' => (string) ($attributes['calendar_data'] ?? ''),
            'etag' => (string) ($attributes['etag'] ?? ''),
            'size' => (int) ($attributes['size'] ?? 0),
            'uid' => $attributes['uid'] ?? null,
            'componentType' => $attributes['component_type'] ?? null,
            'startsAt' => $attributes['starts_at'] ?? null,
            'endsAt' => $attributes['ends_at'] ?? null,
            'isAllDay' => $attributes['is_all_day'] ?? false,
            'timezone' => $attributes['timezone'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{data: string, uid: string|null, component_type: string|null, starts_at: mixed, ends_at: mixed, is_all_day: bool, timezone: string|null}
     *
     * @throws JsonException
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $data = $value instanceof CalendarObjectData ? $value : DtoFactory::calendarObjectData(is_array($value) ? $value : []);

        return [
            'data' => json_encode(DtoFactory::calendarObjectStorageData($data), JSON_THROW_ON_ERROR),
            'uid' => $data->uid,
            'component_type' => $data->componentType,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'is_all_day' => $data->isAllDay,
            'timezone' => $data->timezone,
        ];
    }
}
