<?php

namespace Bambamboole\LaravelDav\Dto;

final readonly class PrincipalData
{
    public function __construct(
        public string $uri,
        public string|int $id,
        public ?string $displayName = null,
        public ?string $email = null,
    ) {}
}
