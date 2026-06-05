<?php

namespace Bambamboole\LaravelDav\Facades;

use Bambamboole\LaravelDav\LaravelDav;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @method static class-string<Model> ownerModel()
 * @method static string ownerTable()
 */
class Dav extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LaravelDav::class;
    }
}
