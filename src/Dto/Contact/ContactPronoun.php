<?php

namespace Bambamboole\LaravelDav\Dto\Contact;

use Bambamboole\LaravelDav\Dto\Contact\Concerns\NormalizesContactData;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
class ContactPronoun implements Arrayable, JsonSerializable
{
    use NormalizesContactData;

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

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
