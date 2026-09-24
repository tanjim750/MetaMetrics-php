<?php

declare(strict_types=1);

namespace MetaMetrics\Authentication;

final readonly class AuthenticationResult
{
    public function __construct(private string $accountId)
    {
    }

    public function authenticated(): bool
    {
        return true;
    }

    public function accountAccessible(): bool
    {
        return true;
    }

    public function accountId(): string
    {
        return $this->accountId;
    }
}
