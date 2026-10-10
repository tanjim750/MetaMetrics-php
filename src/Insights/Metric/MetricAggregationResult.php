<?php

declare(strict_types=1);

namespace MetaMetrics\Insights\Metric;

use JsonSerializable;
use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\InsightsLevel;

final readonly class MetricAggregationResult implements JsonSerializable
{
    /** @param array<string, float|null> $metrics */
    public function __construct(
        private string $entityId,
        private InsightsLevel $level,
        private DateRange $dateRange,
        private array $metrics,
        private ?string $currency = null,
    ) {
    }

    public function entityId(): string { return $this->entityId; }
    public function currency(): ?string { return $this->currency; }

    public function jsonSerialize(): array { return $this->toArray(); }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'entity_id' => $this->entityId,
            'level' => $this->level->value,
            'currency' => $this->currency,
            'date_start' => $this->dateRange->since(),
            'date_stop' => $this->dateRange->until(),
            ...$this->metrics,
        ];
    }
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
