<?php

namespace Bambamboole\LaravelDav\Sabre\Concerns;

use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavChange;
use Bambamboole\LaravelDav\Server\SyncTokens;
use Bambamboole\LaravelDav\Support\DavChangeOperation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

trait RecordsDavChanges
{
    private function davSyncToken(int $syncToken): string
    {
        return SyncTokens::Prefix.$syncToken;
    }

    private function parseDavSyncToken(string $syncToken): ?int
    {
        if (ctype_digit($syncToken)) {
            return (int) $syncToken;
        }

        if (! str_starts_with($syncToken, SyncTokens::Prefix)) {
            return null;
        }

        $value = mb_substr($syncToken, mb_strlen(SyncTokens::Prefix));

        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param  Collection<int, DavChange>  $changes
     * @return array{syncToken: string, added: array<int, string>, modified: array<int, string>, deleted: array<int, string>, result_truncated: bool}
     */
    private function davChangeResponse(int $syncToken, Collection $changes, bool $resultTruncated = false): array
    {
        $states = [];

        foreach ($changes as $change) {
            if ($change->resource_uri === null) {
                continue;
            }

            $currentState = $states[$change->resource_uri] ?? null;

            $states[$change->resource_uri] = match ($change->operation) {
                DavChangeOperation::Add->value => 'added',
                DavChangeOperation::Modify->value => $currentState === 'added' ? 'added' : 'modified',
                DavChangeOperation::Delete->value => 'deleted',
                default => $currentState,
            };
        }

        $result = [
            'syncToken' => $this->davSyncToken($syncToken),
            'added' => [],
            'modified' => [],
            'deleted' => [],
            'result_truncated' => $resultTruncated,
        ];

        foreach ($states as $uri => $state) {
            if (is_string($state)) {
                $result[$state][] = $uri;
            }
        }

        return $result;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return array{syncToken: string, added: array<int, string>, modified: array<int, string>, deleted: array<int, string>, result_truncated: bool}
     */
    private function currentResourceChangeResponse(int $currentSyncToken, Builder $query, mixed $limit = null): array
    {
        $syncLimit = $this->syncLimit($limit);

        if ($syncLimit !== null) {
            $query->limit($syncLimit + 1);
        }

        $uris = $query->pluck('uri')->all();
        $resultTruncated = $syncLimit !== null && count($uris) > $syncLimit;

        if ($resultTruncated) {
            $uris = array_slice($uris, 0, $syncLimit);
        }

        return [
            'syncToken' => $this->davSyncToken($currentSyncToken),
            'added' => $uris,
            'modified' => [],
            'deleted' => [],
            'result_truncated' => $resultTruncated,
        ];
    }

    /**
     * @return array{syncToken: string, added: array<int, string>, modified: array<int, string>, deleted: array<int, string>, result_truncated: bool}|null
     */
    private function changedResourceResponse(DavCalendar|DavAddressBook $collection, string $type, string $syncToken, mixed $limit = null): ?array
    {
        $requestedToken = $this->parseDavSyncToken($syncToken);
        $currentSyncToken = (int) $collection->sync_token;

        if ($requestedToken === null || $requestedToken > $currentSyncToken) {
            return null;
        }

        $syncLimit = $this->syncLimit($limit);
        $query = DavChange::query()
            ->where('collection_type', $type)
            ->where('collection_id', $collection->getKey())
            ->where('sync_token', '>', $requestedToken)
            ->where('sync_token', '<=', $currentSyncToken)
            ->orderBy('sync_token')
            ->orderBy('id');

        if ($syncLimit !== null) {
            $query->limit($syncLimit + 1);
        }

        $changes = $query->get();
        $resultTruncated = $syncLimit !== null && $changes->count() > $syncLimit;

        if ($resultTruncated) {
            $changes = $changes->take($syncLimit);
        }

        $responseSyncToken = $resultTruncated
            ? (int) $changes->last()?->sync_token
            : $currentSyncToken;

        return $this->davChangeResponse($responseSyncToken, $changes, $resultTruncated);
    }

    private function syncLimit(mixed $limit): ?int
    {
        if (! is_numeric($limit)) {
            return null;
        }

        $limit = (int) $limit;

        return $limit > 0 ? $limit : null;
    }
}
