<?php

declare(strict_types=1);

namespace MetaMetrics\Insights\Metric;

use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\InsightsLevel;

final readonly class MetricAggregationResult
{
    /** @param array<string, float|null> $metrics */
    public function __construct(
        private string $entityId,
        private InsightsLevel $level,
        private DateRange $dateRange,
        private array $metrics,
    ) {
    }

    public function entityId(): string { return $this->entityId; }
    public function level(): InsightsLevel { return $this->level; }
    public function dateRange(): DateRange { return $this->dateRange; }

    public function metric(Metric $metric): ?float
    {
        return $this->metrics[$metric->value] ?? null;
    }

    /** @return array<string, float|null> */
    public function metrics(): array
    {
        return $this->metrics;
    }
}
