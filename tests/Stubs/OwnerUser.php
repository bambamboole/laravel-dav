<?php

namespace Bambamboole\LaravelDav\Tests\Stubs;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class OwnerUser extends Authenticatable
{
    use HasFactory;

    protected $table = 'users';

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'password'];

    protected static function newFactory(): OwnerUserFactory
    {
        return OwnerUserFactory::new();
    }
}
