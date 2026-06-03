<?php

use Bambamboole\LaravelDav\Contracts\DavOwner;
use Bambamboole\LaravelDav\Sabre\Concerns\RecordsDavChanges;
use Bambamboole\LaravelDav\Sabre\Concerns\ResolvesPrincipalUri;
use Bambamboole\LaravelDav\Server\SyncTokens;

beforeEach(function (): void {
    $this->principals = new class
    {
        use ResolvesPrincipalUri;

        public function uri(DavOwner|int|string $owner): string
        {
            return $this->principalUri($owner);
        }

        public function userId(string $principalUri): ?int
        {
            return $this->userIdFromPrincipalUri($principalUri);
        }
    };

    $this->tokens = new class
    {
        use RecordsDavChanges;

        public function make(int $syncToken): string
        {
            return $this->davSyncToken($syncToken);
        }

        public function parse(string $syncToken): ?int
        {
            return $this->parseDavSyncToken($syncToken);
        }
    };
});

it('builds a principal uri from a numeric owner', function (): void {
    expect($this->principals->uri(42))->toBe('principals/42');
});

it('builds a principal uri from a DavOwner instance', function (): void {
    $owner = new class implements DavOwner
    {
        public function getDavPrincipalId(): string|int
        {
            return 7;
        }

        public function getDavPrincipalDisplayName(): string
        {
            return 'Test Owner';
        }

        public function getDavPrincipalEmail(): ?string
        {
            return 'owner@example.test';
        }
    };

    expect($this->principals->uri($owner))->toBe('principals/7');
});

it('resolves the owner id from a principal uri', function (): void {
    expect($this->principals->userId('principals/42'))->toBe(42);
});

it('rejects malformed principal uris', function (string $principalUri): void {
    expect($this->principals->userId($principalUri))->toBeNull();
})->with([
    'non-numeric segment' => ['principals/x'],
    'wrong prefix' => ['foo/42'],
    'nested path' => ['principals/4/2'],
]);

it('formats a sync token with the sabre prefix', function (): void {
    expect($this->tokens->make(5))->toBe(SyncTokens::Prefix.'5');
});

it('parses bare and prefixed sync tokens', function (): void {
    expect($this->tokens->parse('5'))->toBe(5);
    expect($this->tokens->parse(SyncTokens::Prefix.'5'))->toBe(5);
});

it('rejects garbage sync tokens', function (string $token): void {
    expect($this->tokens->parse($token))->toBeNull();
})->with([
    'non-numeric' => ['nope'],
    'prefixed non-numeric' => [SyncTokens::Prefix.'abc'],
    'prefixed empty' => [SyncTokens::Prefix],
]);
