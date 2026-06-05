<?php

namespace Bambamboole\LaravelDav\Exceptions;

use RuntimeException;

class StaleDavResourceException extends RuntimeException
{
    public function __construct(
        public readonly ?string $expectedEtag = null,
        public readonly ?string $actualEtag = null,
        public readonly ?string $resourceUri = null,
    ) {
        parent::__construct('The DAV resource was modified before this write was applied.');
    }
}
