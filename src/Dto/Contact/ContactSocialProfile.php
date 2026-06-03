<?php

namespace Bambamboole\LaravelDav\Dto\Contact;

use Bambamboole\LaravelDav\Dto\Contact\Concerns\NormalizesContactData;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
class ContactSocialProfile implements Arrayable, JsonSerializable
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
        $this->url = $this->nullableString($data, 'url');
        $this->userIdentifier = $this->nullableString($data, 'user_identifier') ?? $this->nullableString($data, 'userIdentifier');
        $this->group = $this->nullableString($data, 'group');
    }

    public ?string $label;

    public ?string $service;

    public ?string $username;

    public ?string $url;

    public ?string $userIdentifier;

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
            'url' => $this->url,
            'user_identifier' => $this->userIdentifier,
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
