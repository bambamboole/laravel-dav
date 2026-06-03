<?php

namespace Bambamboole\LaravelDav\Dto\Contact;

use Bambamboole\LaravelDav\Dto\Contact\Concerns\NormalizesContactData;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
class ContactVCardExtension implements Arrayable, JsonSerializable
{
    use NormalizesContactData;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->name = $this->string($data, 'name');
        $this->value = $this->string($data, 'value');
        $this->group = $this->nullableString($data, 'group');
        $this->parameters = is_array($data['parameters'] ?? null) ? $data['parameters'] : [];
    }

    public string $name;

    public string $value;

    public ?string $group;

    /** @var array<string, array<int, string>|string> */
    public array $parameters;

    /**
     * @return array{name: string, value: string, group: ?string, parameters: array<string, array<int, string>|string>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'group' => $this->group,
            'parameters' => $this->parameters,
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
