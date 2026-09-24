<?php

declare(strict_types=1);

namespace MetaMetrics\Client\Pagination;

final readonly class PaginationResult
{
    /** @param list<array<string, mixed>> $items */
    public function __construct(
        private array $items,
        private ?string $nextCursor,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function items(): array
    {
        return $this->items;
    }

    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }

    public function hasNextPage(): bool
    {
        return $this->nextCursor !== null;
    }
}
