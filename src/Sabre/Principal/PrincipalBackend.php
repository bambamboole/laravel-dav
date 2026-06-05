<?php

namespace Bambamboole\LaravelDav\Sabre\Principal;

use Bambamboole\LaravelDav\Contracts\DavOwner;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Sabre\Concerns\ResolvesPrincipalUri;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Sabre\DAV\PropPatch;
use Sabre\DAVACL\PrincipalBackend\AbstractBackend;

class PrincipalBackend extends AbstractBackend
{
    use ResolvesPrincipalUri;

    private const DisplayNameProperty = '{DAV:}displayname';

    private const EmailAddressProperty = '{http://sabredav.org/ns}email-address';

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
        $ownerId = $this->userIdFromPrincipalUri((string) $path);

        if ($ownerId === null) {
            return null;
        }

        $owner = (Dav::ownerModel())::query()->find($ownerId);

        return $owner instanceof DavOwner ? $this->principalForOwner($owner) : null;
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
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function getGroupMembership($principal): array
    {
        return [];
    }

    /**
     * @param  array<int, string>  $members
     */
    public function setGroupMemberSet($principal, array $members): void
    {
        //
    }

    /**
     * @return array{id: int|string, uri: string, '{DAV:}displayname': string|null, '{http://sabredav.org/ns}email-address': string|null}
     */
    private function principalForOwner(DavOwner $owner): array
    {
        return [
            'id' => $owner->getDavPrincipalId(),
            'uri' => $this->principalUri($owner),
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
}
