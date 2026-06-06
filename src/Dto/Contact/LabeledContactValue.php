<?php

namespace Bambamboole\LaravelDav\Dto\Contact;

abstract class LabeledContactValue extends ContactValue
{
    public ?string $label;

    public string $value;

    /** @var array<int, string> */
    public array $types;

    public bool $isPreferred;

    public ?string $group;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->label = $this->nullableString($data, 'label');
        $this->value = $this->string($data, 'value');
        $this->types = $this->stringList($data, 'types');
        $this->isPreferred = $this->bool($data, 'isPreferred');
        $this->group = $this->nullableString($data, 'group');
    }
}
