<?php

namespace Bambamboole\LaravelDav\Server;

use Sabre\DAV;
use Sabre\DAV\PropFind;
use Sabre\DAV\Sync\ISyncCollection;
use Sabre\DAV\Sync\Plugin as BaseSyncPlugin;
use Sabre\DAV\Xml\Request\SyncCollectionReport;
use Sabre\HTTP\RequestInterface;

class SyncPlugin extends BaseSyncPlugin
{
    public function syncCollection($uri, SyncCollectionReport $report): void
    {
        $node = $this->server->tree->getNodeForPath($uri);

        if (! $node instanceof ISyncCollection) {
            throw new DAV\Exception\ReportNotSupported('The {DAV:}sync-collection REPORT is not supported on this url.');
        }

        if (! $node->getSyncToken()) {
            throw new DAV\Exception\ReportNotSupported('No sync information is available at this node');
        }

        $syncToken = $report->syncToken;

        if ($syncToken !== null && ! str_starts_with($syncToken, SyncTokens::Prefix)) {
            throw new DAV\Exception\InvalidSyncToken('Invalid or unknown sync token');
        }

        $changeInfo = $node->getChanges($syncToken, $report->syncLevel, $report->limit);

        if ($changeInfo === null) {
            throw new DAV\Exception\InvalidSyncToken('Invalid or unknown sync token');
        }

        $resultTruncated = (bool) ($changeInfo['result_truncated'] ?? false);

        $this->sendSyncCollectionResponse(
            $changeInfo['syncToken'],
            $uri,
            $changeInfo['added'],
            $changeInfo['modified'],
            $changeInfo['deleted'],
            $report->properties,
            $resultTruncated,
        );
    }

    /**
     * @param  array<int, string>  $added
     * @param  array<int, string>  $modified
     * @param  array<int, string>  $deleted
     * @param  array<int, string>  $properties
     */
    protected function sendSyncCollectionResponse($syncToken, $collectionUrl, array $added, array $modified, array $deleted, array $properties, bool $resultTruncated = false): void
    {
        $fullPaths = [];

        foreach (array_merge($added, $modified) as $item) {
            $fullPaths[] = $collectionUrl.'/'.$item;
        }

        $responses = [];

        foreach ($this->server->getPropertiesForMultiplePaths($fullPaths, $properties) as $fullPath => $props) {
            $responses[] = new DAV\Xml\Element\Response($fullPath, $props);
        }

        foreach ($deleted as $item) {
            $responses[] = new DAV\Xml\Element\Response($collectionUrl.'/'.$item, [], '404');
        }

        if ($resultTruncated) {
            $responses[] = new DAV\Xml\Element\Response($collectionUrl.'/', [], '507');
        }

        $multiStatus = new DAV\Xml\Response\MultiStatus($responses, $syncToken);

        $this->server->httpResponse->setStatus(207);
        $this->server->httpResponse->setHeader('Content-Type', 'application/xml; charset=utf-8');
        $this->server->httpResponse->setBody(
            $this->server->xml->write('{DAV:}multistatus', $multiStatus, $this->server->getBaseUri())
        );
    }

    public function propFind(PropFind $propFind, DAV\INode $node): void
    {
        $propFind->handle('{DAV:}sync-token', function () use ($node): ?string {
            if (! $node instanceof ISyncCollection) {
                return null;
            }

            $token = $node->getSyncToken();

            return is_string($token) ? $this->formatSyncToken($token) : null;
        });
    }

    /**
     * @param  array<int, array{uri?: string, tokens?: array<int, array{token?: string, validToken?: bool}>}>  $conditions
     *
     * @param-out array<int, array{uri?: string, tokens?: array<int, array{token?: string, validToken?: bool}>}> $conditions
     */
    public function validateTokens(RequestInterface $request, &$conditions): void
    {
        foreach ($conditions as $conditionIndex => $condition) {
            foreach ($condition['tokens'] ?? [] as $tokenIndex => $token) {
                if (! isset($token['token'], $condition['uri'])) {
                    continue;
                }

                if (! str_starts_with($token['token'], SyncTokens::Prefix)) {
                    continue;
                }

                $node = $this->server->tree->getNodeForPath($condition['uri']);

                if (
                    $node instanceof ISyncCollection
                    && $this->formatSyncToken((string) $node->getSyncToken()) === $token['token']
                ) {
                    $conditions[$conditionIndex]['tokens'][$tokenIndex]['validToken'] = true;
                }
            }
        }
    }

    private function formatSyncToken(string $syncToken): string
    {
        if (str_starts_with($syncToken, SyncTokens::Prefix)) {
            return $syncToken;
        }

        return SyncTokens::Prefix.$syncToken;
    }
}
