<?php

declare(strict_types=1);

namespace MetaMetrics\AdSet;

use DateTimeImmutable;

final readonly class AdSetDTO
{
    public function __construct(
        private string $id,
        private string $name,
        private string $campaignId,
        private ?string $status,
        private ?string $configuredStatus,
        private ?string $effectiveStatus,
        private ?string $optimizationGoal,
        private ?string $billingEvent,
        private ?string $dailyBudget,
        private ?string $lifetimeBudget,
        private ?DateTimeImmutable $createdTime,
        private ?DateTimeImmutable $updatedTime,
        private ?DateTimeImmutable $startTime,
        private ?DateTimeImmutable $endTime,
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

    public function optimizationGoal(): ?string
    {
        return $this->optimizationGoal;
    }

    public function billingEvent(): ?string
    {
        return $this->billingEvent;
    }

    public function dailyBudget(): ?string
    {
        return $this->dailyBudget;
    }

    public function lifetimeBudget(): ?string
    {
        return $this->lifetimeBudget;
    }

    public function createdTime(): ?DateTimeImmutable
    {
        return $this->createdTime;
    }

    public function updatedTime(): ?DateTimeImmutable
    {
        return $this->updatedTime;
    }

    public function startTime(): ?DateTimeImmutable
    {
        return $this->startTime;
    }

    public function endTime(): ?DateTimeImmutable
    {
        return $this->endTime;
    }
}
