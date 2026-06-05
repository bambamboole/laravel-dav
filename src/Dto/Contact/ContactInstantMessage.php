<?php

namespace Bambamboole\LaravelDav\Dto\Contact;

use Bambamboole\LaravelDav\Dto\Contact\Concerns\NormalizesContactData;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
class ContactInstantMessage implements Arrayable, JsonSerializable
{
    use NormalizesContactData;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->label = $this->nullableString($data, 'label');
        $this->service = $this->nullableString($data, 'service');
        $this->username = $this->nullableString($data, 'username');
        $this->uri = $this->nullableString($data, 'uri');
        $this->types = $this->stringList($data, 'types');
        $this->isPreferred = $this->bool($data, 'isPreferred');
        $this->group = $this->nullableString($data, 'group');
    }

    public ?string $label;

    public ?string $service;

    public ?string $username;

    public ?string $uri;

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
            'service' => $this->service,
            'username' => $this->username,
            'uri' => $this->uri,
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
