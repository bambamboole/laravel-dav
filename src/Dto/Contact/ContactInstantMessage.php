<?php

namespace Bambamboole\LaravelDav\Dto\Contact;

class ContactInstantMessage extends ContactValue
{
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
}
