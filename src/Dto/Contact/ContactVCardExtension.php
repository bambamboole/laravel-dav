<?php

namespace Bambamboole\LaravelDav\Dto\Contact;

class ContactVCardExtension extends ContactValue
{
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
}
