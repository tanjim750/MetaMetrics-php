<?php

declare(strict_types=1);

namespace MetaMetrics\Account;

final readonly class AccountDTO
{
    public function __construct(
        private string $id,
        private string $name,
        private string $accountId,
        private ?int $accountStatus,
        private ?string $currency,
        private ?string $timezoneName,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function accountStatus(): ?int
    {
        return $this->accountStatus;
    }

    public function currency(): ?string
    {
        return $this->currency;
    }

    public function timezoneName(): ?string
    {
        return $this->timezoneName;
    }
}
