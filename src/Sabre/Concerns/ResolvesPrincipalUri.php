<?php

namespace Bambamboole\LaravelDav\Sabre\Concerns;

use Bambamboole\LaravelDav\Contracts\DavOwner;

trait ResolvesPrincipalUri
{
    private function userIdFromPrincipalUri(string $principalUri): ?int
    {
        $prefix = config('dav.principal_prefix', 'principals');

        if (! str_starts_with($principalUri, $prefix.'/')) {
            return null;
        }

        $userId = mb_substr($principalUri, mb_strlen($prefix) + 1);

        if ($userId === '' || str_contains($userId, '/') || ! ctype_digit($userId)) {
            return null;
        }

        return (int) $userId;
    }

    private function principalUri(DavOwner|int|string $owner): string
    {
        $ownerId = $owner instanceof DavOwner ? $owner->getDavPrincipalId() : $owner;

        return config('dav.principal_prefix', 'principals').'/'.$ownerId;
    }
}
