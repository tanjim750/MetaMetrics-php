<?php

declare(strict_types=1);

namespace MetaMetrics\Historical;

use MetaMetrics\Insights\InsightsDTO;
use MetaMetrics\Insights\Metric\Metric;

final readonly class DeliveryDiscovery implements DeliveryDiscoveryInterface
{
    private const EVIDENCE_METRICS = [
        Metric::IMPRESSIONS,
        Metric::SPEND,
        Metric::REACH,
    ];

    /** @return list<Metric> */
    public function requiredMetrics(): array
    {
        return self::EVIDENCE_METRICS;
    }

    public function assess(InsightsDTO $insights): DeliveryAssessment
    {
        $indicators = [];
        $available = 0;

        foreach (self::EVIDENCE_METRICS as $metric) {
            $value = $insights->metric($metric);

            if ($value === null) {
                continue;
            }

            ++$available;

            if ($value > 0.0) {
                $indicators[$metric->value] = $value;
            }
        }

        if ($indicators !== []) {
            return DeliveryAssessment::delivered(new DeliveryEvidence($indicators));
        }

        return $available === 0
            ? DeliveryAssessment::insufficientEvidence()
            : DeliveryAssessment::notDelivered();
    }

    public function hasDelivery(InsightsDTO $insights): bool
    {
        return $this->assess($insights)->status() === DeliveryStatus::DELIVERED;
    }
}
