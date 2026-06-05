<?php

namespace Bambamboole\LaravelDav\Sabre\Locks;

use Bambamboole\LaravelDav\Models\DavLock;
use Illuminate\Database\Eloquent\Builder;
use Sabre\DAV\Locks\Backend\BackendInterface;
use Sabre\DAV\Locks\LockInfo;

class LockBackend implements BackendInterface
{
    /** @return array<int, LockInfo> */
    public function getLocks($uri, $returnChildLocks)
    {
        return DavLock::query()
            ->whereRaw('created > (? - timeout)', [time()])
            ->where(function (Builder $query) use ($uri, $returnChildLocks): void {
                $query->where('uri', $uri);

                foreach ($this->parentUris($uri) as $parentUri) {
                    $query->orWhere(fn (Builder $query): Builder => $query
                        ->where('depth', '!=', 0)
                        ->where('uri', $parentUri));
                }

                if ($returnChildLocks) {
                    $query->orWhere('uri', 'like', $uri.'/%');
                }
            })
            ->get(['owner', 'token', 'timeout', 'created', 'scope', 'depth', 'uri'])
            ->map(fn (DavLock $lock): LockInfo => $this->toLockInfo($lock))
            ->all();
    }

    public function lock($uri, LockInfo $lockInfo)
    {
        $this->purgeExpiredLocks();

        $lockInfo->timeout = 30 * 60;
        $lockInfo->created = time();
        $lockInfo->uri = $uri;

        DavLock::query()->updateOrCreate(
            ['token' => $lockInfo->token],
            [
                'owner' => $lockInfo->owner,
                'timeout' => $lockInfo->timeout,
                'created' => $lockInfo->created,
                'scope' => $lockInfo->scope,
                'depth' => $lockInfo->depth,
                'uri' => $uri,
            ],
        );

        return true;
    }

    public function unlock($uri, LockInfo $lockInfo)
    {
        return DavLock::query()
            ->where('uri', $uri)
            ->where('token', $lockInfo->token)
            ->delete() > 0;
    }

    /** @return array<int, string> */
    private function parentUris(string $uri): array
    {
        $parts = explode('/', $uri);
        array_pop($parts);

        $parents = [];
        $current = '';

        foreach ($parts as $part) {
            $current = $current === '' ? $part : $current.'/'.$part;
            $parents[] = $current;
        }

        return $parents;
    }

    private function toLockInfo(DavLock $lock): LockInfo
    {
        $lockInfo = new LockInfo;
        $lockInfo->owner = $lock->owner;
        $lockInfo->token = $lock->token;
        $lockInfo->timeout = $lock->timeout;
        $lockInfo->created = $lock->created;
        $lockInfo->scope = $lock->scope;
        $lockInfo->depth = $lock->depth;
        $lockInfo->uri = $lock->uri;

        return $lockInfo;
    }

    private function purgeExpiredLocks(): void
    {
        DavLock::query()
            ->whereRaw('created <= (? - timeout)', [time()])
            ->delete();
    }
}
