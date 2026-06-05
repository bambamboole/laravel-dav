<?php

namespace Bambamboole\LaravelDav\Dto\Contact;

class ContactSocialProfile extends ContactValue
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->label = $this->nullableString($data, 'label');
        $this->service = $this->nullableString($data, 'service');
        $this->username = $this->nullableString($data, 'username');
        $this->url = $this->nullableString($data, 'url');
        $this->userIdentifier = $this->nullableString($data, 'userIdentifier');
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
            'userIdentifier' => $this->userIdentifier,
            'group' => $this->group,
        ];
    }
}
