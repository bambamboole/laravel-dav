<?php

use Bambamboole\LaravelDav\Models\DavLock;
use Bambamboole\LaravelDav\Sabre\Locks\LockBackend;
use Illuminate\Support\Facades\DB;
use Sabre\DAV\Locks\LockInfo;
use Sabre\DAV\Server;

function lockInfo(string $token = 'token-1', int $depth = 0, ?string $owner = '<d:href xmlns:d="DAV:">/users/1</d:href>'): LockInfo
{
    $lock = new LockInfo;
    $lock->owner = $owner;
    $lock->token = $token;
    $lock->timeout = 600;
    $lock->created = now()->timestamp;
    $lock->scope = LockInfo::EXCLUSIVE;
    $lock->depth = $depth;

    return $lock;
}

it('creates and returns a lock for a uri', function (): void {
    $backend = new LockBackend;

    $backend->lock('calendars/1/personal/event.ics', lockInfo());

    $locks = $backend->getLocks('calendars/1/personal/event.ics', false);

    expect($locks)->toHaveCount(1)
        ->and($locks[0])
        ->token->toBe('token-1')
        ->owner->toBe('<d:href xmlns:d="DAV:">/users/1</d:href>')
        ->uri->toBe('calendars/1/personal/event.ics')
        ->timeout->toBe(1800)
        ->scope->toBe(LockInfo::EXCLUSIVE)
        ->depth->toBe(0);
});

it('returns parent and child locks for a uri', function (): void {
    $backend = new LockBackend;

    $backend->lock('calendars/1/personal', lockInfo('parent-token', Server::DEPTH_INFINITY));
    $backend->lock('calendars/1/personal/event.ics/attachment', lockInfo('child-token'));

    $locks = $backend->getLocks('calendars/1/personal/event.ics', true);

    expect(collect($locks)->pluck('token')->all())->toContain('parent-token', 'child-token');
});

it('ignores expired locks and purges them before writing', function (): void {
    DavLock::factory()->create([
        'uri' => 'calendars/1/personal/event.ics',
        'token' => 'expired-token',
        'timeout' => 60,
        'created' => now()->subMinutes(2)->timestamp,
    ]);

    $backend = new LockBackend;

    expect($backend->getLocks('calendars/1/personal/event.ics', false))->toBeEmpty();

    $backend->lock('calendars/1/personal/event.ics', lockInfo('fresh-token'));

    expect(DB::table('dav_locks')->where('token', 'expired-token')->count())->toBe(0)
        ->and(DB::table('dav_locks')->where('token', 'fresh-token')->count())->toBe(1);
});

it('refreshes and unlocks an existing lock', function (): void {
    $backend = new LockBackend;

    $backend->lock('calendars/1/personal/event.ics', lockInfo('token-1'));
    $backend->lock('calendars/1/personal/event.ics', lockInfo('token-1', Server::DEPTH_INFINITY, null));

    expect(DB::table('dav_locks')->where('token', 'token-1')->count())->toBe(1)
        ->and(DB::table('dav_locks')->where('token', 'token-1')->value('depth'))->toBe(Server::DEPTH_INFINITY)
        ->and(DB::table('dav_locks')->where('token', 'token-1')->value('owner'))->toBeNull();

    expect($backend->unlock('calendars/1/personal/event.ics', lockInfo('token-1')))->toBeTrue()
        ->and($backend->unlock('calendars/1/personal/event.ics', lockInfo('token-1')))->toBeFalse();
});
