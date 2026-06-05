<?php

namespace Bambamboole\LaravelDav\Support;

enum DavChangeOperation: int
{
    case Add = 1;
    case Modify = 2;
    case Delete = 3;

    public function label(): string
    {
        return match ($this) {
            self::Add => 'added',
            self::Modify => 'modified',
            self::Delete => 'deleted',
        };
    }
}
