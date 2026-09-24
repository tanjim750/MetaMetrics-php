<?php

declare(strict_types=1);

namespace MetaMetrics\Insights\Normalizer;

use MetaMetrics\Insights\InsightsDTO;
use MetaMetrics\Insights\InsightsLevel;
use MetaMetrics\Insights\Metric\Metric;
use MetaMetrics\Insights\Metric\MetricCalculator;

final readonly class InsightsNormalizer
{
    public function __construct(private MetricCalculator $calculator = new MetricCalculator())
    {
    }

    /**
     * @param array{
     *     entityId: string,
     *     entityName: string|null,
     *     campaignId: string|null,
     *     adSetId: string|null,
     *     adId: string|null,
     *     dateRange: \MetaMetrics\Insights\DateRange\DateRange,
     *     foundational: array<string, float|null>,
     *     rawData: \MetaMetrics\Insights\InsightsRawData
     * } $parsed
     * @param list<Metric> $requestedMetrics
     */
    public function normalize(array $parsed, InsightsLevel $level, array $requestedMetrics): InsightsDTO
    {
        return new InsightsDTO(
            entityId: $parsed['entityId'],
            entityName: $parsed['entityName'],
            level: $level,
            campaignId: $parsed['campaignId'],
            adSetId: $parsed['adSetId'],
            adId: $parsed['adId'],
            dateRange: $parsed['dateRange'],
            metrics: $this->calculator->calculateAll($requestedMetrics, $parsed['foundational']),
            rawData: $parsed['rawData'],
            foundationalMetrics: $parsed['foundational'],
        );
    }
}
