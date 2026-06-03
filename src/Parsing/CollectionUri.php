<?php

namespace Bambamboole\LaravelDav\Parsing;

use Illuminate\Support\Str;

class CollectionUri
{
    public static function fromDisplayName(string $displayName): string
    {
        return Str::slug($displayName).'-'.Str::lower(Str::random(6));
    }
}
