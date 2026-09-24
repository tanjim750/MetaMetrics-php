<?php

declare(strict_types=1);

namespace MetaMetrics\Client;

final readonly class Request
{
    /**
     * @param array<string, scalar|list<scalar>|null> $query
     */
    public function __construct(
        private string $method,
        private string $path,
        private array $query = [],
    ) {
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, scalar|list<scalar>|null>
     */
    public function query(): array
    {
        return $this->query;
    }

    public function withQueryParameter(string $name, mixed $value): self
    {
        return new self(
            method: $this->method,
            path: $this->path,
            query: [...$this->query, $name => $value],
        );
    }
}
