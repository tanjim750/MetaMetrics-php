<?php

declare(strict_types=1);

namespace MetaMetrics\Client;

final readonly class Response
{
    public function __construct(
        private int $statusCode,
        private mixed $body,
    ) {
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): mixed
    {
        return $this->body;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
