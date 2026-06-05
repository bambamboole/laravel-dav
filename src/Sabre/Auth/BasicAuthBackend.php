<?php

namespace Bambamboole\LaravelDav\Sabre\Auth;

use Bambamboole\LaravelDav\Contracts\DavOwner;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavCredential;
use Bambamboole\LaravelDav\Sabre\Concerns\ResolvesPrincipalUri;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Sabre\DAV\Auth\Backend\AbstractBasic;
use Sabre\HTTP;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class BasicAuthBackend extends AbstractBasic
{
    use ResolvesPrincipalUri;

    private ?Model $owner = null;

    private ?string $currentUser = null;

    public function __construct()
    {
        $this->setRealm((string) config('dav.realm'));
    }

    public function user(): ?Model
    {
        return $this->owner;
    }

    /**
     * @return array{0: bool, 1: string}
     */
    public function check(RequestInterface $request, ResponseInterface $response): array
    {
        $this->owner = null;
        $this->currentUser = null;

        $auth = new HTTP\Auth\Basic(
            $this->realm,
            $request,
            $response
        );

        $userpass = $auth->getCredentials();

        if (! $userpass) {
            return [false, "No 'Authorization: Basic' header found. Either the client didn't send one, or the server is misconfigured"];
        }

        if (! $this->validateUserPass($userpass[0], $userpass[1])) {
            return [false, 'Username or password was incorrect'];
        }

        return [true, $this->currentUser ?? ''];
    }

    protected function validateUserPass($username, $password): bool
    {
        $credential = Dav::modelFor('credential', DavCredential::class)::query()
            ->where('username', $username)
            ->with('owner')
            ->first();

        if (! $credential || ! Hash::check($password, $credential->secret_hash)) {
            return false;
        }

        $credential->forceFill([
            'last_used_at' => now(),
        ])->save();

        $owner = $credential->owner;

        $this->owner = $owner;
        $this->currentUser = $this->principalUri(
            $owner instanceof DavOwner ? $owner : $owner->getKey(),
        );

        return true;
    }
}
