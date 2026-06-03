<?php

use Bambamboole\LaravelDav\Sabre\Principal\PrincipalBackend;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;

it('lists owners as principals with display name and email from the contract', function (): void {
    $first = OwnerUser::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    $second = OwnerUser::factory()->create(['name' => 'Alan Turing', 'email' => 'alan@example.com']);

    $principals = (new PrincipalBackend)->getPrincipalsByPrefix('principals');

    expect($principals)->toHaveCount(2)
        ->and($principals[0]['uri'])->toBe('principals/'.$first->getKey())
        ->and($principals[0]['{DAV:}displayname'])->toBe('Ada Lovelace')
        ->and($principals[0]['{http://sabredav.org/ns}email-address'])->toBe('ada@example.com')
        ->and($principals[1]['uri'])->toBe('principals/'.$second->getKey())
        ->and($principals[1]['{DAV:}displayname'])->toBe('Alan Turing');
});

it('resolves a single principal by path', function (): void {
    $owner = OwnerUser::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

    $principal = (new PrincipalBackend)->getPrincipalByPath('principals/'.$owner->getKey());

    expect($principal)->not->toBeNull()
        ->and($principal['id'])->toBe($owner->getKey())
        ->and($principal['{DAV:}displayname'])->toBe('Grace Hopper')
        ->and($principal['{http://sabredav.org/ns}email-address'])->toBe('grace@example.com');
});

it('searches principals by display name through the contract getters', function (): void {
    OwnerUser::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
    $turing = OwnerUser::factory()->create(['name' => 'Alan Turing', 'email' => 'alan@example.com']);

    $matches = (new PrincipalBackend)->searchPrincipals('principals', [
        '{DAV:}displayname' => 'Turing',
    ]);

    expect($matches)->toBe(['principals/'.$turing->getKey()]);
});

it('finds a principal by mailto uri', function (): void {
    $owner = OwnerUser::factory()->create(['email' => 'find@example.com']);

    $uri = (new PrincipalBackend)->findByUri('mailto:find@example.com', 'principals');

    expect($uri)->toBe('principals/'.$owner->getKey());
});
