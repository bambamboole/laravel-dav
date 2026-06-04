<?php

namespace Bambamboole\LaravelDav\Facades;

use Bambamboole\LaravelDav\LaravelDav;
use Illuminate\Support\Facades\Facade;

class Dav extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LaravelDav::class;
    }
}
