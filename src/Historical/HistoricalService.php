<?php

declare(strict_types=1);

namespace MetaMetrics\Historical;

use MetaMetrics\Exception\UnexpectedResponseException;
use MetaMetrics\Insights\DateRange\DateRange;
use MetaMetrics\Insights\InsightsQuery;
use MetaMetrics\Insights\InsightsService;

final readonly class HistoricalService
{
    public function __construct(
        private InsightsService $insightsService,
        private DeliveryDiscoveryInterface $deliveryDiscovery = new DeliveryDiscovery(),
    ) {
    }

    /** @return list<HistoricalResult> */
    public function get(HistoricalQuery $query): array
    {
        $insights = $this->insightsService->get(new InsightsQuery(
            level: $query->level()->toInsightsLevel(),
            dateRange: $query->dateRange(),
            metrics: $this->effectiveMetrics($query),
            entityId: $query->parentId(),
            daily: $query->isDaily(),
            filters: $query->filters(),
            limit: $query->limit(),
            includeAdSetConfiguration: false,
        ));
        $results = [];

        foreach ($insights as $insight) {
            $assessment = $this->deliveryDiscovery->assess($insight);

            if ($assessment->status() === DeliveryStatus::INSUFFICIENT_EVIDENCE) {
                throw new UnexpectedResponseException(sprintf(
                    'Meta returned insufficient delivery evidence for %s "%s".',
                    $query->level()->value,
                    $insight->entityId(),
                ));
            }

            $evidence = $assessment->evidence();

            if ($evidence === null) {
                continue;
            }

            $results[] = new HistoricalResult(
                requestedPeriod: $query->dateRange(),
                deliveryEvidence: $evidence,
                insights: $insight,
            );
        }

        return $results;
    }

    /** @return list<HistoricalResult> */
    public function campaigns(DateRange $dateRange): array
    {
        return $this->get(new HistoricalQuery(HistoricalLevel::CAMPAIGN, $dateRange));
    }

    /** @return list<HistoricalResult> */
    public function adSets(DateRange $dateRange, ?string $campaignId = null): array
    {
        return $this->get(new HistoricalQuery(
            HistoricalLevel::AD_SET,
            $dateRange,
            parentId: $campaignId,
        ));
    }

    /** @return list<HistoricalResult> */
    public function ads(DateRange $dateRange, ?string $parentId = null): array
    {
        return $this->get(new HistoricalQuery(
            HistoricalLevel::AD,
            $dateRange,
            parentId: $parentId,
        ));
    }

    /** @return list<\MetaMetrics\Insights\Metric\Metric> */
    private function effectiveMetrics(HistoricalQuery $query): array
    {
        $metrics = [];

        foreach ([...$this->deliveryDiscovery->requiredMetrics(), ...$query->metrics()] as $metric) {
            $metrics[$metric->value] = $metric;
        }

        return array_values($metrics);
    }
}
