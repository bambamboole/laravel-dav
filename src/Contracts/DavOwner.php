<?php

namespace Bambamboole\LaravelDav\Contracts;

interface DavOwner
{
    public function getDavPrincipalId(): string|int;

    public function getDavPrincipalDisplayName(): string;

    public function getDavPrincipalEmail(): ?string;
}
