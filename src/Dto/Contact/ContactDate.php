<?php

namespace Bambamboole\LaravelDav\Dto\Contact;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class ContactDate extends ContactValue implements Castable
{
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
        $this->rawValue = $this->nullableString($data, 'rawValue');
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
                $date = $value instanceof ContactDate
                    ? $value
                    : (is_array($value) ? new ContactDate($value) : null);

                return $date !== null ? json_encode($date->toArray()) : null;
            }
        };
    }
}
