<?php

declare(strict_types=1);

namespace MetaMetrics\Client\Pagination;

use MetaMetrics\Authentication\MetaErrorMapper;
use MetaMetrics\Client\MetaClientInterface;
use MetaMetrics\Client\Request;
use MetaMetrics\Exception\UnexpectedResponseException;

final readonly class Paginator
{
    public function __construct(
        private MetaClientInterface $client,
        private MetaErrorMapper $errorMapper = new MetaErrorMapper(),
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function fetchAll(Request $request): array
    {
        $items = [];
        $seenCursors = [];

        do {
            $page = $this->fetchPage($request);
            array_push($items, ...$page->items());

            $cursor = $page->nextCursor();

            if ($cursor !== null) {
                if (isset($seenCursors[$cursor])) {
                    throw new UnexpectedResponseException('Meta returned a repeated pagination cursor.');
                }

                $seenCursors[$cursor] = true;
                $request = $request->withQueryParameter('after', $cursor);
            }
        } while ($cursor !== null);

        return $items;
    }

    public function fetchPage(Request $request): PaginationResult
    {
        $response = $this->client->send($request);

        if (!$response->isSuccessful()) {
            throw $this->errorMapper->map($response);
        }

        $body = $response->body();

        if (!is_array($body) || !isset($body['data']) || !is_array($body['data']) || !array_is_list($body['data'])) {
            throw new UnexpectedResponseException('Meta returned a malformed paginated response.');
        }

        foreach ($body['data'] as $item) {
            if (!is_array($item)) {
                throw new UnexpectedResponseException('Meta returned a malformed collection item.');
            }
        }

        return new PaginationResult($body['data'], $this->nextCursor($body));
    }

    /** @param array<string, mixed> $body */
    private function nextCursor(array $body): ?string
    {
        $paging = $body['paging'] ?? null;

        if (!is_array($paging) || !isset($paging['next'])) {
            return null;
        }

        $cursor = $paging['cursors']['after'] ?? null;

        if (!is_string($cursor) || $cursor === '') {
            throw new UnexpectedResponseException('Meta pagination is missing its next cursor.');
        }

        return $cursor;
    }
}
