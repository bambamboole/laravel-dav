<?php

namespace Bambamboole\LaravelDav\Dto\Contact;

use Bambamboole\LaravelDav\Dto\Contact\Concerns\NormalizesContactData;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
class ContactPostalAddress implements Arrayable, JsonSerializable
{
    use NormalizesContactData;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->label = $this->nullableString($data, 'label');
        $this->poBox = $this->nullableString($data, 'poBox');
        $this->extended = $this->nullableString($data, 'extended');
        $this->street = $this->nullableString($data, 'street');
        $this->city = $this->nullableString($data, 'city');
        $this->region = $this->nullableString($data, 'region');
        $this->postalCode = $this->nullableString($data, 'postalCode');
        $this->country = $this->nullableString($data, 'country');
        $this->countryCode = $this->nullableString($data, 'countryCode');
        $this->types = $this->stringList($data, 'types');
        $this->isPreferred = $this->bool($data, 'isPreferred');
        $this->group = $this->nullableString($data, 'group');
    }

    public ?string $label;

    public ?string $poBox;

    public ?string $extended;

    public ?string $street;

    public ?string $city;

    public ?string $region;

    public ?string $postalCode;

    public ?string $country;

    public ?string $countryCode;

    /** @var array<int, string> */
    public array $types;

    public bool $isPreferred;

    public ?string $group;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'poBox' => $this->poBox,
            'extended' => $this->extended,
            'street' => $this->street,
            'city' => $this->city,
            'region' => $this->region,
            'postalCode' => $this->postalCode,
            'country' => $this->country,
            'countryCode' => $this->countryCode,
            'types' => $this->types,
            'isPreferred' => $this->isPreferred,
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
