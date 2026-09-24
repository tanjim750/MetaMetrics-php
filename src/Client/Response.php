<?php

declare(strict_types=1);

namespace MetaMetrics\Client;

use MetaMetrics\Exception\InvalidInputException;

final readonly class Response
{
    /** @var array<string, string> */
    private array $headers;

    /** @param array<string, string> $headers */
    public function __construct(
        private int $statusCode,
        private mixed $body,
        private ?string $rawBody = null,
        array $headers = [],
    ) {
        $normalized = [];

        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                throw new InvalidInputException('Response headers must be strings.');
            }

            $normalized[strtolower($name)] = $value;
        }

        $this->headers = $normalized;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): mixed
    {
        return $this->body;
    }

    public function rawBody(): ?string
    {
        return $this->rawBody;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200
            && $this->statusCode < 300
            && !(is_array($this->body) && array_key_exists('error', $this->body));
    }
}
