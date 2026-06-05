<?php

namespace Bambamboole\LaravelDav\Exceptions;

use RuntimeException;

class StaleDavResourceException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The DAV resource was modified before this write was applied.');
    }
}
