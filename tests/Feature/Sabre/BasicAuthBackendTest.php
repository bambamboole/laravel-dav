<?php

use Bambamboole\LaravelDav\Models\DavCredential;
use Bambamboole\LaravelDav\Sabre\Auth\BasicAuthBackend;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;
use Illuminate\Support\Facades\Hash;

function authBackend(): object
{
    return new class extends BasicAuthBackend
    {
        public function validate(string $username, string $password): bool
        {
            return $this->validateUserPass($username, $password);
        }

        public function currentPrincipal(): ?string
        {
            $owner = $this->user();

            return $owner ? 'principals/'.$owner->getKey() : null;
        }
    };
}

it('authenticates a valid credential and touches last_used_at', function (): void {
    $owner = OwnerUser::factory()->create();
    $credential = DavCredential::factory()->create([
        'user_id' => $owner->getKey(),
        'username' => 'caldav-user',
        'secret_hash' => Hash::make('s3cret'),
        'last_used_at' => null,
    ]);

    $backend = authBackend();

    expect($backend->validate('caldav-user', 's3cret'))->toBeTrue()
        ->and($backend->user()->getKey())->toBe($owner->getKey())
        ->and($backend->currentPrincipal())->toBe('principals/'.$owner->getKey());

    expect($credential->fresh()->last_used_at)->not->toBeNull();
});

it('rejects a wrong secret without touching last_used_at', function (): void {
    $credential = DavCredential::factory()->create([
        'username' => 'caldav-user',
        'secret_hash' => Hash::make('s3cret'),
        'last_used_at' => null,
    ]);

    $backend = authBackend();

    expect($backend->validate('caldav-user', 'wrong'))->toBeFalse()
        ->and($backend->user())->toBeNull();

    expect($credential->fresh()->last_used_at)->toBeNull();
});
