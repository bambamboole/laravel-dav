<?php

namespace Bambamboole\LaravelDav\Dto\Contact;

use Bambamboole\LaravelDav\Dto\Contact\Concerns\NormalizesContactData;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
class ContactDate implements Arrayable, Castable, JsonSerializable
{
    use NormalizesContactData;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->label = $this->nullableString($data, 'label');
        $this->year = $this->nullableInt($data, 'year');
        $this->month = $this->nullableInt($data, 'month');
        $this->day = $this->nullableInt($data, 'day');
        $this->calendar = $this->nullableString($data, 'calendar');
        $this->rawValue = $this->nullableString($data, 'raw_value') ?? $this->nullableString($data, 'rawValue');
        $this->group = $this->nullableString($data, 'group');
    }

    public ?string $label;

    public ?int $year;

    public ?int $month;

    public ?int $day;

    public ?string $calendar;

    public ?string $rawValue;

    public ?string $group;

    /**
     * @param  array<int, mixed>  $arguments
     */
    /**
     * @return CastsAttributes<ContactDate|null, string|null>
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            /**
             * @param  array<string, mixed>  $attributes
             */
            public function get(Model $model, string $key, mixed $value, array $attributes): ?ContactDate
            {
                if ($value === null || $value === '') {
                    return null;
                }

                $data = is_array($value) ? $value : json_decode((string) $value, true);

                return is_array($data) ? new ContactDate($data) : null;
            }

            /**
             * @param  array<string, mixed>  $attributes
             */
            public function set(Model $model, string $key, mixed $value, array $attributes): ?string
            {
                if ($value === null) {
                    return null;
                }

                if ($value instanceof ContactDate) {
                    return json_encode($value->toArray());
                }

                if (is_array($value)) {
                    return json_encode((new ContactDate($value))->toArray());
                }

                return null;
            }
        };
    }

    /**
     * @return array{label: ?string, year: ?int, month: ?int, day: ?int, calendar: ?string, raw_value: ?string, group: ?string}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'year' => $this->year,
            'month' => $this->month,
            'day' => $this->day,
            'calendar' => $this->calendar,
            'raw_value' => $this->rawValue,
            'group' => $this->group,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
