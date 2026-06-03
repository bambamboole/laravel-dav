<?php

namespace Bambamboole\LaravelDav\Dto\Contact;

use Bambamboole\LaravelDav\Dto\Contact\Concerns\NormalizesContactData;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
class ContactEmailAddress implements Arrayable, JsonSerializable
{
    use NormalizesContactData;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->label = $this->nullableString($data, 'label');
        $this->value = $this->string($data, 'value');
        $this->types = $this->stringList($data, 'types');
        $this->isPreferred = $this->bool($data, 'is_preferred') || $this->bool($data, 'isPreferred');
        $this->group = $this->nullableString($data, 'group');
    }

    public ?string $label;

    public string $value;

    /** @var array<int, string> */
    public array $types;

    public bool $isPreferred;

    public ?string $group;

    /**
     * @return array{label: ?string, value: string, types: array<int, string>, is_preferred: bool, group: ?string}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
            'types' => $this->types,
            'is_preferred' => $this->isPreferred,
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
