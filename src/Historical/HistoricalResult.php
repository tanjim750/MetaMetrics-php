<?php

declare(strict_types=1);

namespace MetaMetrics\Historical;

use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\InsightsDTO;
use MetaMetrics\Insights\Metric\Metric;

final readonly class HistoricalResult
{
    public function __construct(
        private DateRange $requestedPeriod,
        private DeliveryEvidence $deliveryEvidence,
        private InsightsDTO $insights,
    ) {
        if (HistoricalLevel::tryFrom($this->insights->level()->value) === null) {
            throw new InvalidInputException('Historical results require Campaign, Ad Set, or Ad Insights.');
        }

        if ($this->insights->dateStart() < $this->requestedPeriod->since()
            || $this->insights->dateStop() > $this->requestedPeriod->until()) {
            throw new InvalidInputException('Historical Insights must fall within the requested reporting period.');
        }

        foreach ($this->deliveryEvidence->indicators() as $name => $value) {
            if ($this->insights->metric(Metric::from($name)) !== $value) {
                throw new InvalidInputException('Historical delivery evidence must match its Insights metrics.');
            }
        }
    }

    public function entityId(): string
    {
        return $this->insights->entityId();
    }

    public function entityName(): ?string
    {
        return $this->insights->entityName();
    }

    public function level(): HistoricalLevel
    {
        return HistoricalLevel::from($this->insights->level()->value);
    }

    public function campaignId(): ?string
    {
        return $this->insights->campaignId();
    }

    public function adSetId(): ?string
    {
        return $this->insights->adSetId();
    }

    public function adId(): ?string
    {
        return $this->insights->adId();
    }

    public function requestedPeriod(): DateRange
    {
        return $this->requestedPeriod;
    }

    public function requestedSince(): string
    {
        return $this->requestedPeriod->since();
    }

    public function requestedUntil(): string
    {
        return $this->requestedPeriod->until();
    }

    public function deliveryEvidence(): DeliveryEvidence
    {
        return $this->deliveryEvidence;
    }

    public function insights(): InsightsDTO
    {
        return $this->insights;
    }
}
