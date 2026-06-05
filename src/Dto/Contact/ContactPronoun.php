<?php

namespace Bambamboole\LaravelDav\Dto\Contact;

class ContactPronoun extends ContactValue
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->language = $this->nullableString($data, 'language');
        $this->value = $this->string($data, 'value');
        $this->group = $this->nullableString($data, 'group');
    }

    public ?string $language;

    public string $value;

    public ?string $group;

    /**
     * @return array{language: ?string, value: string, group: ?string}
     */
    public function toArray(): array
    {
        return [
            'language' => $this->language,
            'value' => $this->value,
            'group' => $this->group,
        ];
    }
}
