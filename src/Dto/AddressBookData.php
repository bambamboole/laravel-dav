<?php

namespace Bambamboole\LaravelDav\Dto;

final readonly class AddressBookData
{
    public function __construct(
        public string $uri,
        public ?string $displayName = null,
        public ?string $description = null,
        public int $syncToken = 1,
    ) {}
}
