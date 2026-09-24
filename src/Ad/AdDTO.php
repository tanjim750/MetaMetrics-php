<?php

declare(strict_types=1);

namespace MetaMetrics\Ad;

use DateTimeImmutable;

final readonly class AdDTO
{
    public function __construct(
        private string $id,
        private string $name,
        private string $adSetId,
        private string $campaignId,
        private ?string $status,
        private ?string $configuredStatus,
        private ?string $effectiveStatus,
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

    public function adSetId(): string
    {
        return $this->adSetId;
    }

    public function campaignId(): string
    {
        return $this->campaignId;
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

    public function createdTime(): ?DateTimeImmutable
    {
        return $this->createdTime;
    }

    public function updatedTime(): ?DateTimeImmutable
    {
        return $this->updatedTime;
    }
}
