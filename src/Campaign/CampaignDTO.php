<?php

declare(strict_types=1);

namespace MetaMetrics\Campaign;

use DateTimeImmutable;

final readonly class CampaignDTO
{
    public function __construct(
        private string $id,
        private string $name,
        private ?string $status,
        private ?string $configuredStatus,
        private ?string $effectiveStatus,
        private ?string $objective,
        private ?DateTimeImmutable $createdTime,
        private ?DateTimeImmutable $updatedTime,
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

    public function status(): ?string
    {
        return $this->status;
    }

    public function configuredStatus(): ?string
    {
        return $this->configuredStatus;
    }

    public function effectiveStatus(): ?string
    {
        return $this->effectiveStatus;
    }

    public function objective(): ?string
    {
        return $this->objective;
    }

    public function createdTime(): ?DateTimeImmutable
    {
        return $this->createdTime;
    }

    public function updatedTime(): ?DateTimeImmutable
    {
        return $this->updatedTime;
    }
}
