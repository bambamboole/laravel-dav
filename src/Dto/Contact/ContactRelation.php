<?php

namespace Bambamboole\LaravelDav\Dto\Contact;

class ContactRelation extends ContactValue
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->label = $this->nullableString($data, 'label');
        $this->name = $this->string($data, 'name');
        $this->group = $this->nullableString($data, 'group');
    }

    public ?string $label;

    public string $name;

    public ?string $group;

    /**
     * @return array{label: ?string, name: string, group: ?string}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'name' => $this->name,
            'group' => $this->group,
        ];
    }
}
