<?php

declare(strict_types=1);

namespace MetaMetrics\Insights;

use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\Metric\Metric;

final readonly class InsightsDTO
{
    /**
     * @param array<string, float|null> $metrics
     * @param array<string, float|null> $foundationalMetrics
     */
    public function __construct(
        private string $entityId,
        private ?string $entityName,
        private InsightsLevel $level,
        private ?string $campaignId,
        private ?string $adSetId,
        private ?string $adId,
        private DateRange $dateRange,
        private array $metrics,
        private InsightsRawData $rawData,
        private array $foundationalMetrics = [],
    ) {
    }

    public function entityId(): string
    {
        return $this->entityId;
    }

    public function entityName(): ?string
    {
        return $this->entityName;
    }

    public function level(): InsightsLevel
    {
        return $this->level;
    }

    public function campaignId(): ?string
    {
        return $this->campaignId;
    }

    public function adSetId(): ?string
    {
        return $this->adSetId;
    }

    public function adId(): ?string
    {
        return $this->adId;
    }

    public function dateRange(): DateRange
    {
        return $this->dateRange;
    }

    public function dateStart(): string
    {
        return $this->dateRange->since();
    }

    public function dateStop(): string
    {
        return $this->dateRange->until();
    }

    public function hasMetric(Metric $metric): bool
    {
        return array_key_exists($metric->value, $this->metrics);
    }

    public function metric(Metric $metric): ?float
    {
        return $this->metrics[$metric->value] ?? null;
    }

    /** @return array<string, float|null> */
    public function metrics(): array
    {
        return $this->metrics;
    }

    public function rawData(): InsightsRawData
    {
        return $this->rawData;
    }

    public function foundationalMetric(Metric $metric): ?float
    {
        return $this->foundationalMetrics[$metric->value] ?? null;
    }

    /** @return array<string, float|null> */
    public function foundationalMetrics(): array
    {
        return $this->foundationalMetrics;
    }

    public function spend(): ?float { return $this->metric(Metric::SPEND); }
    public function purchases(): ?float { return $this->metric(Metric::PURCHASES); }
    public function costPerPurchase(): ?float { return $this->metric(Metric::COST_PER_PURCHASE); }
    public function purchaseValue(): ?float { return $this->metric(Metric::PURCHASE_VALUE); }
    public function roas(): ?float { return $this->metric(Metric::ROAS); }
    public function impressions(): ?float { return $this->metric(Metric::IMPRESSIONS); }
    public function reach(): ?float { return $this->metric(Metric::REACH); }
    public function clicks(): ?float { return $this->metric(Metric::CLICKS); }
    public function linkClicks(): ?float { return $this->metric(Metric::LINK_CLICKS); }
    public function cpc(): ?float { return $this->metric(Metric::CPC); }
    public function ctr(): ?float { return $this->metric(Metric::CTR); }
    public function cpm(): ?float { return $this->metric(Metric::CPM); }
}
