<?php

namespace Bambamboole\LaravelDav\Tests\Stubs;

use Bambamboole\LaravelDav\Contracts\DavOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class OwnerUser extends Authenticatable implements DavOwner
{
    use HasFactory;

    protected $table = 'users';

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'password'];

    public function getDavPrincipalId(): string|int
    {
        return $this->getKey();
    }

    public function getDavPrincipalDisplayName(): string
    {
        return (string) $this->name;
    }

    public function getDavPrincipalEmail(): ?string
    {
        return $this->email;
    }

    protected static function newFactory(): OwnerUserFactory
    {
        return OwnerUserFactory::new();
    }
}
