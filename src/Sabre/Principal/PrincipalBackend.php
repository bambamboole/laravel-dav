<?php

namespace Bambamboole\LaravelDav\Sabre\Principal;

use Bambamboole\LaravelDav\Contracts\DavOwner;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavCalendarProxyMembership;
use Bambamboole\LaravelDav\Sabre\Concerns\ResolvesPrincipalUri;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Sabre\DAV\PropPatch;
use Sabre\DAVACL\PrincipalBackend\AbstractBackend;

class PrincipalBackend extends AbstractBackend
{
    use ResolvesPrincipalUri;

    private const DisplayNameProperty = '{DAV:}displayname';

    private const EmailAddressProperty = '{http://sabredav.org/ns}email-address';

    private const ProxyReadPrincipal = 'calendar-proxy-read';

    private const ProxyWritePrincipal = 'calendar-proxy-write';

    /**
     * @return array<int, array{id: int|string, uri: string, '{DAV:}displayname': string|null, '{http://sabredav.org/ns}email-address': string|null}>
     */
    public function getPrincipalsByPrefix($prefixPath): array
    {
        if ($prefixPath !== config('dav.principal_prefix')) {
            return [];
        }

        return (Dav::ownerModel())::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Model $owner): array => $this->principalForOwner($this->asOwner($owner)))
            ->all();
    }

    /**
     * @return array{id: int|string, uri: string, '{DAV:}displayname': string|null, '{http://sabredav.org/ns}email-address': string|null}|null
     */
    public function getPrincipalByPath($path): ?array
    {
        $path = (string) $path;
        $proxyPrincipal = $this->proxyPrincipal($path);
        $ownerId = $proxyPrincipal === null
            ? $this->userIdFromPrincipalUri($path)
            : $proxyPrincipal['owner_id'];

        if ($ownerId === null) {
            return null;
        }

        $owner = (Dav::ownerModel())::query()->find($ownerId);

        if (! $owner instanceof DavOwner) {
            return null;
        }

        return $this->principalForOwner(
            $owner,
            $proxyPrincipal === null ? null : $path,
        );
    }

    public function updatePrincipal($path, PropPatch $propPatch): int
    {
        return 0;
    }

    /**
     * @param  array<string, mixed>  $searchProperties
     * @return array<int, string>
     */
    public function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof'): array
    {
        if ($prefixPath !== config('dav.principal_prefix')) {
            return [];
        }

        if ($searchProperties === []) {
            return (Dav::ownerModel())::query()
                ->orderBy('id')
                ->get()
                ->map(fn (Model $owner): string => $this->principalUri($this->asOwner($owner)))
                ->all();
        }

        $supportedProperties = array_intersect_key(
            $searchProperties,
            array_flip([self::DisplayNameProperty, self::EmailAddressProperty]),
        );

        if ($supportedProperties === []) {
            return [];
        }

        return (Dav::ownerModel())::query()
            ->orderBy('id')
            ->get()
            ->filter(fn (Model $owner): bool => $this->matchesSearch($this->asOwner($owner), $supportedProperties, $test))
            ->map(fn (Model $owner): string => $this->principalUri($this->asOwner($owner)))
            ->values()
            ->all();
    }

    public function findByUri($uri, $principalPrefix): ?string
    {
        if ($principalPrefix !== config('dav.principal_prefix') || ! str_starts_with($uri, 'mailto:')) {
            return null;
        }

        $email = mb_substr($uri, 7);

        $owner = (Dav::ownerModel())::query()
            ->get()
            ->first(fn (Model $owner): bool => $this->asOwner($owner)->getDavPrincipalEmail() === $email);

        return $owner instanceof DavOwner ? $this->principalUri($owner) : null;
    }

    /**
     * @return array<int, string>
     */
    public function getGroupMemberSet($principal): array
    {
        $proxyPrincipal = $this->proxyPrincipal((string) $principal);

        if ($proxyPrincipal === null) {
            return [];
        }

        return $this->proxyMembershipQuery($proxyPrincipal['owner_id'], $proxyPrincipal['access'])
            ->orderBy('delegate_owner_id')
            ->pluck('delegate_owner_id')
            ->map(fn (int|string $ownerId): string => $this->principalUri($ownerId))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function getGroupMembership($principal): array
    {
        $ownerId = $this->userIdFromPrincipalUri((string) $principal);

        if ($ownerId === null) {
            return [];
        }

        return Dav::model(DavCalendarProxyMembership::class)::query()
            ->where('delegate_owner_id', $ownerId)
            ->orderBy('owner_id')
            ->get()
            ->map(fn (DavCalendarProxyMembership $membership): string => $this->proxyPrincipalUri(
                $membership->owner_id,
                $membership->access,
            ))
            ->all();
    }

    /**
     * @param  array<int, string>  $members
     */
    public function setGroupMemberSet($principal, array $members): void
    {
        $proxyPrincipal = $this->proxyPrincipal((string) $principal);

        if ($proxyPrincipal === null) {
            return;
        }

        $memberOwnerIds = collect($members)
            ->map(fn (string $member): ?int => $this->ownerIdFromMemberUri($member))
            ->filter(fn (?int $ownerId): bool => $ownerId !== null && $this->ownerExists($ownerId))
            ->unique()
            ->values();

        Dav::model(DavCalendarProxyMembership::class)::setDelegates(
            $proxyPrincipal['owner_id'],
            $proxyPrincipal['access'],
            $memberOwnerIds,
        );
    }

    /**
     * @return array{id: int|string, uri: string, '{DAV:}displayname': string|null, '{http://sabredav.org/ns}email-address': string|null}
     */
    private function principalForOwner(DavOwner $owner, ?string $uri = null): array
    {
        return [
            'id' => $owner->getDavPrincipalId(),
            'uri' => $uri ?? $this->principalUri($owner),
            self::DisplayNameProperty => $owner->getDavPrincipalDisplayName(),
            self::EmailAddressProperty => $owner->getDavPrincipalEmail(),
        ];
    }

    /**
     * @param  array<string, mixed>  $searchProperties
     */
    private function matchesSearch(DavOwner $owner, array $searchProperties, string $test): bool
    {
        $matches = [];

        foreach ($searchProperties as $property => $value) {
            $candidate = match ($property) {
                self::DisplayNameProperty => $owner->getDavPrincipalDisplayName(),
                self::EmailAddressProperty => $owner->getDavPrincipalEmail(),
                default => null,
            };

            $matches[] = $candidate !== null
                && str_contains(mb_strtolower($candidate), mb_strtolower((string) $value));
        }

        return $test === 'anyof'
            ? in_array(true, $matches, true)
            : ! in_array(false, $matches, true);
    }

    private function asOwner(Model $model): DavOwner
    {
        if (! $model instanceof DavOwner) {
            throw new RuntimeException(sprintf('The dav.models.owner [%s] must implement %s.', $model::class, DavOwner::class));
        }

        return $model;
    }

    /**
     * @return array{owner_id: int, access: string}|null
     */
    private function proxyPrincipal(string $principal): ?array
    {
        $prefix = config('dav.principal_prefix').'/';

        if (! str_starts_with($principal, $prefix)) {
            return null;
        }

        $segments = explode('/', mb_substr($principal, mb_strlen($prefix)));

        if (count($segments) !== 2 || ! ctype_digit($segments[0])) {
            return null;
        }

        $access = match ($segments[1]) {
            self::ProxyReadPrincipal => DavCalendarProxyMembership::AccessRead,
            self::ProxyWritePrincipal => DavCalendarProxyMembership::AccessWrite,
            default => null,
        };

        return $access === null ? null : [
            'owner_id' => (int) $segments[0],
            'access' => $access,
        ];
    }

    private function ownerIdFromMemberUri(string $member): ?int
    {
        $member = trim($member, '/');
        $baseUri = trim((string) config('dav.base_uri', '/dav/'), '/');

        if ($baseUri !== '' && str_starts_with($member, $baseUri.'/')) {
            $member = mb_substr($member, mb_strlen($baseUri) + 1);
        }

        return $this->userIdFromPrincipalUri($member);
    }

    private function ownerExists(int $ownerId): bool
    {
        return (Dav::ownerModel())::query()->whereKey($ownerId)->exists();
    }

    /**
     * @return Builder<DavCalendarProxyMembership>
     */
    private function proxyMembershipQuery(int $ownerId, string $access): Builder
    {
        return Dav::model(DavCalendarProxyMembership::class)::query()
            ->where('owner_id', $ownerId)
            ->where('access', $access);
    }

    private function proxyPrincipalUri(int|string $ownerId, string $access): string
    {
        $proxyName = $access === DavCalendarProxyMembership::AccessWrite
            ? self::ProxyWritePrincipal
            : self::ProxyReadPrincipal;

        return $this->principalUri($ownerId).'/'.$proxyName;
    }
}
